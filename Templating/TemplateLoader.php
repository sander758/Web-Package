<?php

namespace Wizard\Templating;

use Wizard\App\Controller;
use Wizard\Kernel\App;
use Wizard\Assets\AssetsManager;
use Wizard\Kernel\Http\Controller\ControllerHandler;
use Wizard\Kernel\Http\HttpKernel;
use Wizard\Templating\Exception\TemplateEngineException;
use Wizard\Templating\Exception\TemplateException;

class TemplateLoader
{
    /**
     * @var bool
     * Defines if the templating engine has to be used.
     */
    static $useEngine = false;

    /**
     * @var string
     * The file that holds the template.
     */
    public $cache;
    /**
     * @var string
     * The path of the template
     */
    private $path;

    /**
     * @param $cache
     * TemplateLoader constructor.
     */
    function __construct($cache)
    {
        $this->cache = $cache;
    }

    /**
     * @param string $path
     * @return mixed
     */
    public function loadTemplate(string $path)
    {
        $this->path = $path;
        self::$useEngine = $this->useEngine($path);

        $content = file_get_contents($path);

        if (self::$useEngine) {
            try {
                $engine = new TemplateEngine(App::$root.'/Resources/Views/');
                $content = $engine->parse($content);

                file_put_contents($this->cache, $content);
            } catch (TemplateEngineException $e) {
                $e->showErrorPage();
            }
        } else {
            file_put_contents($this->cache, $content);
        }
        return htmlentities(file_get_contents($this->cache));
    }

    /**
     * @param string $content
     * @param string $asset_name
     * @return array
     *
     * Returns an array with the params
     */
    public function addAssets(string $content, $asset_name)
    {
        $manager = new AssetsManager($asset_name);
        $assets = $manager->load();

        $content = $this->addCss(html_entity_decode($content), $assets['css']);

        $content = $this->addJs(html_entity_decode($content), $assets['js']);

        file_put_contents($this->cache, $content);

        return array('links' => $assets['links'], 'images' => $assets['images']);
    }

    /**
     * @param $content
     * @param $tags
     * @return mixed
     * @throws TemplateException
     *
     * Add the css link tags to the template.
     */
    public function addCss($content, $tags)
    {
        $pos = strpos($content, '<head>');
        if ($pos == 0) {
            //throw new TemplateException('Couldnt add assets because head tag missing');
            return $content;
        }
        foreach ($tags as $tag) {
            $content = substr_replace($content, chr(10).html_entity_decode($tag), $pos + 6, 0);
        }
        return $content;
    }

    /**
     * @param $content
     * @param $tags
     * @return mixed
     * @throws TemplateException
     *
     * Add the js script tags to the template.
     */
    public function addJs($content, $tags)
    {
        $pos = strpos($content, '</body>');
        if ($pos == 0) {
//            throw new TemplateException('Couldnt add assets because body tag missing');
            return $content;
        }
        foreach ($tags as $tag) {
            $content = substr_replace($content, html_entity_decode($tag).chr(10), $pos, 0);
        }
        return $content;
    }

    /**
     * @param array $parameters
     * @return array
     * @throws TemplateException
     *
     * Checks if the parameters array contains specific keys.
     * Also adds a clean controller class.
     */
    public function filterParameters(array $parameters)
    {
        foreach ($parameters as $key => $value) {
            if ($key === 'controller') {
                throw new TemplateException('Cant have controller as parameter key');
            }
            if ($key === 'images') {
                throw new TemplateException('Cant have images as parameter key');
            }
            if ($key === 'links') {
                throw new TemplateException('Cant have links as parameter key');
            }
            if ($key === 'models') {
                throw new TemplateException('Cant have models as parameter key');
            }
        }
        if (!empty(ControllerHandler::$controllerObject)) {
            $parameters['controller'] = ControllerHandler::$controllerObject;
        } else {
            $parameters['controller'] = new Controller(App::$root);
        }
        $parameters['models'] = HttpKernel::$route['models'];

        return $parameters;
    }

    /**
     * @param string $path
     * @return bool
     *
     * Determines on the extension if the template engine has to be used or not.
     */
    private function useEngine(string $path)
    {
        if (!file_exists($path)) {
            return false;
        }
        $exploded = explode('/', $path);
        $exploded_file = explode('.', $exploded[count($exploded) - 1]);
        if (count($exploded_file) == 3 && $exploded_file[1] == 'template') {
            return true;
        }
        return false;
    }
}
