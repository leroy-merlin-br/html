<?php

use Collective\Html\FormBuilder;
use Collective\Html\FormFacade;
use Collective\Html\HtmlBuilder;
use Collective\Html\HtmlFacade;
use Collective\Html\HtmlServiceProvider;
use Illuminate\Container\Container;
use Illuminate\Contracts\Session\Session;
use Illuminate\Contracts\View\Factory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Facade;
use Illuminate\View\Compilers\BladeCompiler;
use Mockery as m;

if (! function_exists('app')) {
    function app($abstract = null, array $parameters = [])
    {
        $container = Container::getInstance();

        if (is_null($abstract)) {
            return $container;
        }

        return $container->make($abstract, $parameters);
    }
}

class HtmlServiceProviderTest extends PHPUnit\Framework\TestCase
{
    private $app;
    private $provider;
    private $request;
    private $session;
    private $urlGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new Container();
        Container::setInstance($this->app);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->app);

        $routes = new RouteCollection();
        $routes->add(new Route(['GET'], 'named/{id}', ['as' => 'named.show']));

        $this->request = Request::create('/integration', 'GET');
        $this->urlGenerator = new UrlGenerator($routes, $this->request);
        $this->session = m::mock(Session::class);

        $this->app->instance('url', $this->urlGenerator);
        $this->app->instance('request', $this->request);
        $this->app->instance('view', m::mock(Factory::class));
        $this->app->instance('session.store', $this->session);
        $this->app->singleton('blade.compiler', function () {
            return new BladeCompiler(new Filesystem(), sys_get_temp_dir());
        });

        $this->provider = new HtmlServiceProvider($this->app);
        $this->provider->register();
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);

        m::close();

        parent::tearDown();
    }

    public function testProviderRegistersHtmlAndFormBindingsAndAliases()
    {
        $this->session->shouldReceive('token')->once()->andReturn('provider-token');

        $html = $this->app->make('html');
        $form = $this->app->make('form');

        $this->assertInstanceOf(HtmlBuilder::class, $html);
        $this->assertInstanceOf(FormBuilder::class, $form);
        $this->assertSame($html, $this->app->make(HtmlBuilder::class));
        $this->assertSame($form, $this->app->make(FormBuilder::class));
        $this->assertSame(['html', 'form', HtmlBuilder::class, FormBuilder::class], $this->provider->provides());
    }

    public function testFormFacadeResolvesContainerBindingsUsingSessionAndRequest()
    {
        $this->session->shouldReceive('token')->once()->andReturn('integration-token');
        $this->session->shouldReceive('getOldInput')->once()->with('_token')->andReturn(null);
        $this->session->shouldReceive('getOldInput')->once()->with('name')->andReturn('Taylor');

        $open = (string) FormFacade::open(['method' => 'POST']);
        $text = (string) FormFacade::text('name');

        $this->assertSame('<form method="POST" action="http://localhost/integration" accept-charset="UTF-8"><input name="_token" type="hidden" value="integration-token">', $open);
        $this->assertSame('<input name="name" type="text" value="Taylor">', $text);
    }

    public function testHtmlFacadeAndGlobalHelpersContinueToResolveThroughTheContainer()
    {
        $this->assertTrue(function_exists('link_to'));
        $this->assertTrue(function_exists('link_to_asset'));
        $this->assertTrue(function_exists('link_to_route'));
        $this->assertTrue(function_exists('link_to_action'));

        $facadeLink = (string) HtmlFacade::link('/docs', 'Docs');
        $helperLink = (string) link_to('/docs', 'Docs');
        $helperAssetLink = (string) link_to_asset('css/app.css', 'Asset');
        $helperRouteLink = (string) link_to_route('named.show', 'Profile', ['id' => 5]);

        $this->assertSame('<a href="http://localhost/docs">Docs</a>', $facadeLink);
        $this->assertSame('<a href="http://localhost/docs">Docs</a>', $helperLink);
        $this->assertSame('<a href="http://localhost/css/app.css">Asset</a>', $helperAssetLink);
        $this->assertSame('<a href="http://localhost/named/5">Profile</a>', $helperRouteLink);
    }

    public function testProviderRegistersBladeDirectivesForHtmlAndFormBuilders()
    {
        $compiler = $this->app->make('blade.compiler');
        $directives = $compiler->getCustomDirectives();

        $this->assertInstanceOf(BladeCompiler::class, $compiler);
        $this->assertArrayHasKey('html_link', $directives);
        $this->assertArrayHasKey('form_open', $directives);
        $this->assertArrayHasKey('form_select_month', $directives);
        $this->assertSame('<?php echo Html::link("/docs", "Docs"); ?>', $directives['html_link']('"/docs", "Docs"'));
        $this->assertSame("<?php echo Form::open(['method' => 'POST']); ?>", $directives['form_open']("['method' => 'POST']"));
        $this->assertSame('<?php echo Form::selectMonth("month"); ?>', $directives['form_select_month']('"month"'));
    }
}

