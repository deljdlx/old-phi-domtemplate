<?php

namespace Phi\DOMTemplate;


use Phi\DOM\Document;

class Template
{


    protected $output;
    protected $template;


    protected $renderer;
    protected static $staticRenderer;


    protected $libXMLFlag;

    /**
     * @var Document
     */
    protected $dom;

    protected $simpleXML;


    protected $rootNode;





    public function __construct($template = null)
    {
        $this->libXMLFlag =
            \LIBXML_HTML_NOIMPLIED
            | \LIBXML_HTML_NODEFDTD
            | \LIBXML_NOXMLDECL
            | \LIBXML_NOENT
            | \LIBXML_NOERROR
            | \LIBXML_NOWARNING
            | \LIBXML_ERR_NONE;

        $this->setTemplate($template);
    }







    public function setTemplate($template)
    {
        $this->template = $template;
        $this->loadString((string) $this->template);
        return $this;
    }




    public function createDomDocumentFromNode(\DOMElement $node)
    {

        $valueNode = $node->cloneNode(true);

        $valueDocument = new Document('1.0', 'utf-8');
        $importedValueNode = $valueDocument->importNode($valueNode, true);
        $valueDocument->appendChild($importedValueNode);

        return $valueDocument;

    }




    public function getHTML()
    {
        $dom = $this->dom;
        $output=$dom->saveHTML();

        //"bug" with saveHTML and "src" attribute
        //replacing {{{  }}}} by %7B%7B%7B    %7D%7D%7D
        $output=urldecode($output);
        return $output;
    }

    public function loadString($buffer)
    {
        libxml_use_internal_errors(true);
        $dom = new Document('1.0', 'utf-8');

        $dom->substituteEntities=false;
        $dom->preserveWhiteSpace=false;
        $dom->formatOutput=true;
        $dom->xmlStandalone=true;

        $dom->loadHTML(mb_convert_encoding($buffer, 'HTML-ENTITIES', 'UTF-8'), $this->libXMLFlag);
        libxml_clear_errors();


        $this->rootNode = $dom->firstChild;
        $this->dom = $dom;

        return $this;
    }


    public function find($query)
    {
        $dom = $this->dom;
        $converter = new CSSToXPath($query);
        $xpathQuery = $converter->getXPath();
        $xPath = new \DOMXPath($dom);
        $nodes = $xPath->query($xpathQuery);
        return $nodes;
    }

    public function each($query, $callback)
    {

        $nodes = $this->find($query);

        $simpleXMLElements = array();

        foreach ($nodes as $key => $node) {


            $simpleXMLElement = new SimpleXMLElement(
               $this->dom->saveXML($node)
            );


            $simpleXMLElements[$key] = array(
                'source' => $node,
                'copy' => $simpleXMLElement
            );
            $callback($simpleXMLElement, $key);

        }


        foreach ($simpleXMLElements as $descriptor) {

            $newNode = dom_import_simplexml($descriptor['copy']);

            $newNode = $this->dom->importNode($newNode, true);

            $this->dom->replaceNodeWithNode(
                $descriptor['source'],
                $newNode
            );
        }


        return $this;


    }






    //=======================================================
    public function setRenderer($renderer)
    {
        $this->renderer = $renderer;
        return $this;
    }

    public static function setStaticRenderer($renderer)
    {
        static::$staticRenderer = $renderer;
        return static::$staticRenderer;
    }

    //=======================================================


    /**
     * @param null $template
     * @param null $values
     * @param null $renderer
     * @return string
     */
    public function render()
    {
        $output = $this->getHTML();
        $this->output = $this->doAfterRendering($output);
        return $this->output;
    }

    public function initializeRendering($template = null, $values = null, $renderer = null)
    {
        if ($renderer) {
            $this->renderer = $renderer;
        }

        if ($template) {
            $this->template = $template;
        }

        if (count($values)) {
            $this->setVariables($values);
        }
    }


    public function doAfterRendering($buffer)
    {

        if (is_callable(static::$staticRenderer)) {
            $buffer = call_user_func_array(static::$staticRenderer, array($buffer, $this));
        }

        if (is_callable($this->renderer)) {
            $buffer = call_user_func_array($this->renderer, array($buffer, $this));
        }
        return $buffer;
    }


    /**
     * @param null $template
     * @param null $values
     * @return string
     */
    public function getOutput($template = null, $values = null, $renderer = null)
    {
        if ($this->output === null) {
            return $this->render($template, $values, $renderer);
        } else {
            return $this->output;
        }
    }


    public function includeTemplate($templateFile, $values=array())
    {
        if (!is_file($templateFile)) {
            throw new \LogicException('Template "' . $templateFile . '" does not exist');
        }
        extract($values);
        ob_start();
        include($templateFile);
        return ob_get_clean();
    }




}
