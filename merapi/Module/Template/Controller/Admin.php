<?php

namespace MerapiPanel\Module\Template\Controller;

use MerapiPanel\Box;
use MerapiPanel\Core\Abstract\Module;
use MerapiPanel\Core\Section;
use MerapiPanel\Core\View\Component\ProcessingComponent;
use MerapiPanel\Core\View\View;
use MerapiPanel\Event;
use MerapiPanel\Module\Template\Custom\TemplateFunction;
use MerapiPanel\Utility\Http\Request;
use Twig\Loader\ArrayLoader;

class Admin extends Module
{

    protected $box;

    function setBox(Box $box)
    {
        $this->box = $box;
        View::AddExtension(new TemplateFunction());
    }


    public function register($router)
    {

        $router->get("/template/initial-scripts/", "getInitialScripts", self::class);

        $router->get("/template/view/{id}/", "viewTemplate", self::class);
        $router->get("/template/edit/{id}/", "editTemplate", self::class);
        $router->get("/template/create", "createNewTemplate", self::class);

        $router->get("/template/endpoint", "fetchTemplate", self::class);
        $router->post("/template/endpoint", "saveTemplate", self::class);

        $router->get("/template/fetchall/", "fetchAll", self::class);

        $router->delete("/template/delete/", "deleteTemplate", self::class);



        /**
         * Register menu component
         */
        $route = $router->get("/template", "index", self::class);
        $panel = $this->box->Module_Panel();
        $panel->addMenu([
            'order' => 3,
            "parent" => "",
            'name' => "Template",
            'icon' => 'fa-solid fa-brush',
            'link' => $route->getPath()
        ]);
    }


    function getInitialScripts()
    {
        return [
            "code" => 200,
            "data" => $this->service()->getInitialScript()
        ];
    }



    public function componentFetch(Request $req)
    {

        $root       = realpath(__DIR__ . "/../../") . "\\**\\template\\component.php";
        $components = [];

        foreach (glob($root) as $file) {

            $className  = "MerapiPanel\Module\{**}\Template\Component";

            $file = str_replace("\\", "/", $file);
            $file = trim(str_replace(str_replace("\\", "/", realpath(__DIR__ . "/../../")), "", $file), "/");
            [$module_name] = explode("/", $file);
            $className     = str_replace("{**}", $module_name, $className);

            if (class_exists($className)) {

                $instance     = new $className();
                $components[] = [
                    "name" => $module_name,
                    "components" => $instance->getAvailableMethods()
                ];
            }
        }


        return [
            "code"    => 200,
            "message" => "Ok",
            "data"    => $components
        ];
    }





    public function componentRender(Request $req)
    {
        $addr = $req->addr();
        ob_start();

        $body = $req->getRequestBody();
        $params = json_encode($body);

        $loader = new ArrayLoader([
            "template" => "{{ guest.$addr($params, {edit: true}) | raw }}"
        ]);

        // Initialize the Twig environment
        $twig = new \Twig\Environment($loader, []);

        $guest = new Section("guest");

        $twig->addGlobal("guest", $guest);
        $htmlString = $twig->render('template', []);
        ob_end_clean();


        return [
            "code"    => 200,
            "message" => "Ok",
            "data"    => [
                'output' => $htmlString
            ]
        ];
    }







    public function index($req)
    {
        return View::render("index.html.twig");
    }



    public function createNewTemplate(Request $req)
    {
        return View::render("editor.html.twig");
    }


    public function fetchTemplate(Request $req)
    {

        $id       = $req->id();
        $service  = $this->service();
        $template = $service->getTemplate($id);

        if (!$template) {
            return [
                "code" => 404,
                "message" => "Template not found"
            ];
        }

        $html = $template['html'];
        $css  = $template['css'];


        // prevent rendering for initial editing
        $element = null;
        Event::on(ProcessingComponent::class, ProcessingComponent::ON_BEFORE_PROCESSING, function ($matches) use (&$element) {
            $element = $matches;
        });
        Event::on(ProcessingComponent::class, ProcessingComponent::ON_AFTER_PROCESSING, function ($module, $method, $arguments, &$output) use (&$element) {
            $output = $element;
        });


        $htmlString = View::newInstance(new ArrayLoader([
            "template" => file_get_contents(__DIR__ . "/../$html")
        ]))->load("template")->render();


        return [
            "data" => [
                "html" => $htmlString,
                "css" => $css
            ]
        ];
        return View::render("view.html.twig", [
            "template" => $template
        ]);
    }


    public function fetchAll()
    {

        $service = $this->service();
        return [
            "code" => 200,
            "data" => [
                "templates" => $service->getAllTemplate()
            ],
            "message" => "Templates fetched successfully"
        ];
    }

    public function viewTemplate(Request $request)
    {

        $id = $request->id();
        $service = $this->service();
        $template = $service->getTemplate($id);

        if (!$template) {
            return [
                "code" => 404,
                "message" => "Template not found"
            ];
        }

        return View::render("view.html.twig", [
            "template" => [
                "html" => $template['html'],
                "css" => $template['css']
            ]
        ]);
    }



    public function editTemplate(Request $request)
    {
        $id = $request->getParam("id");
        $service = $this->service();

        return View::render("editor.html.twig", [
            "template" => $service->getTemplate($id)
        ]);
    }



    public function saveTemplate(Request $request)
    {

        $panel = $this->box->Module_Panel();
        $service = $this->service();
        $BODY    = $request->getRequestBody();

        if (!isset($BODY['name']) || empty($BODY['name'])) { {
                return [
                    "code" => 400,
                    "message" => "Name is required"
                ];
            }
        }

        $id = uniqid();
        $name = $BODY['name'];
        $descript = $BODY['description'];
        $htmldata = $BODY['htmldata'];
        $cssdata  = $BODY['cssdata'];


        if (isset($BODY['id'])) {
            return $this->updateTemplate([
                "id" => $BODY['id'],
                "name" => $name,
                "description" => $descript,
                "htmldata" => $htmldata,
                "cssdata" => $cssdata
            ]);
        }


        if ($service->saveTemplate($id, $name, $descript, $htmldata, $cssdata)) {

            return [
                "code" => 200,
                "message" => "Template created successfully",
                "data" => [
                    "params" => [
                        "name" => $name,
                        "description" => $descript,
                        "id" => $id,
                    ],
                    "state" => [
                        'data'  => [],
                        'title' => "Edit Template",
                        'url'   => $panel->adminLink("/template/edit/" . $id)
                    ]
                ]
            ];
        } else {
            return [
                "code" => 400,
                "message" => "Error saving template"
            ];
        }
    }



    function updateTemplate($opt = [
        "id" => "",
        "name" => "",
        "description" => "",
        "htmldata" => "",
        "cssdata" => ""
    ])
    {

        $service = $this->service();
        $update = $service->updateTemplate($opt['id'], $opt['name'], $opt['description'], $opt['htmldata'], $opt['cssdata']);

        if ($update) {
            return [
                "code" => 200,
                "message" => "Template updated successfully",
                "data" => [
                    "params" => [
                        "name" => $opt['name'],
                        "description" => $opt['description'],
                        "id" => $opt['id'],
                    ]
                ]
            ];
        } else {
            return [
                "code" => 400,
                "message" => "Error updating template"
            ];
        }
    }




    function deleteTemplate(Request $request)
    {

        $id = $request->id();

        if (!$this->service()->deleteTemplate($id)) {
            return [
                "code" => 400,
                "message" => "Error deleting template"
            ];
        } else {
            return [
                "code" => 200,
                "message" => "Template deleted successfully",
            ];
        }
    }
}