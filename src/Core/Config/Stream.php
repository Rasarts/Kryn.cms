<?php

namespace Core\Config;

class Stream extends Model
{

    /**
     * @var string
     */
    protected $class;

    /**
     * @var string
     */
    protected $method;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var string
     */
    protected $id;

    public function setupObject()
    {
        $this->setVar('class');
        $this->setVar('method');
        $this->setAttributeVar('id');
        $this->id = $this->element->attributes->getNamedItem('id')->nodeValue;
    }

    /**
     * @param string $class
     */
    public function setClass($class)
    {
        $this->class = $class;
    }

    /**
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @param bool $fqn Full qualified name
     *
     * @return string
     */
    public function getId($fqn = false)
    {
        return ($fqn ? $this->getBundleName() . '/' : '') . $this->id;
    }

    /**
     * @param string $label
     */
    public function setLabel($label)
    {
        $this->label = $label;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @param string $method
     */
    public function setMethod($method)
    {
        $this->method = $method;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    public function run(&$response) {
        $callable = array($this->getClass(), $this->getMethod());

        $parameters = array(&$response);
        call_user_func_array($callable, $parameters);
    }

}