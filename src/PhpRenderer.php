<?php
/**
 * Slim Framework (http://slimframework.com)
 *
 * @link      https://github.com/slimphp/PHP-View
 * @copyright Copyright (c) 2011-2015 Josh Lockhart
 * @license   https://github.com/slimphp/PHP-View/blob/master/LICENSE.md (MIT License)
 */
namespace Slim\Views;

use \InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

/**
 * Class PhpRenderer
 * @package Slim\Views
 *
 * Render PHP view scripts into a PSR-7 Response object
 */
class PhpRenderer
{
	/**
	 * @var string
	 */
	protected $templatePath;

	/**
	 * @var array
	 */
	protected $attributes;

	/**
	 * SlimRenderer constructor.
	 *
	 * @param string $templatePath
	 * @param array $attributes
	 */
	public function __construct($templatePath = "", $attributes = [])
	{
		$this->templatePath = rtrim($templatePath, '/\\') . '/';
		$this->attributes = $attributes;
	}

	/**
	 * Render a template
	 *
	 * $data cannot contain template as a key
	 *
	 * throws RuntimeException if $templatePath . $template does not exist
	 *
	 * @param ResponseInterface $response
	 * @param string             $template
	 * @param array              $data
	 *
	 * @return ResponseInterface
	 *
	 * @throws \InvalidArgumentException
	 * @throws \RuntimeException
	 */
	public function render(ResponseInterface $response, $template, array $data = [])
	{
		$output = $this->fetch($template, $data);
		if (
			!empty($_COOKIE['templateHelper']) && $_COOKIE['templateHelper'] == 1
			&&
			(
				empty($_SERVER['HTTP_X_REQUESTED_WITH'])
				||
				strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest'
			)
		){
			$output .= rv($this->used_templates, '', 4);
			//$output .= rv($_COOKIE);
		}

		$response->getBody()->write($output);

		return $response;
	}

	/**
	 * Get the attributes for the renderer
	 *
	 * @return array
	 */
	public function getAttributes()
	{
		return $this->attributes;
	}

	/**
	 * Set the attributes for the renderer
	 *
	 * @param array $attributes
	 */
	public function setAttributes(array $attributes)
	{
		$this->attributes = $attributes;
	}

	/**
	 * Add an attribute
	 *
	 * @param $key
	 * @param $value
	 */
	public function addAttribute($key, $value) {
		$this->attributes[$key] = $value;
	}

	/**
	 * Retrieve an attribute
	 *
	 * @param $key
	 * @return mixed
	 */
	public function getAttribute($key) {
		if (!isset($this->attributes[$key])) {
			return false;
		}

		return $this->attributes[$key];
	}

	/**
	 * Get the template path
	 *
	 * @return string
	 */
	public function getTemplatePath()
	{
		return $this->templatePath;
	}

	/**
	 * Set the template path
	 *
	 * @param string $templatePath
	 */
	public function setTemplatePath($templatePath)
	{
        	$this->templatePath = rtrim($templatePath, '/\\') . '/';
	}

	/**
	 * Renders a template and returns the result as a string
	 *
	 * cannot contain template as a key
	 *
	 * throws RuntimeException if $templatePath . $template does not exist
	 *
	 * @param $template
	 * @param array $data
	 * @return false|mixed|string
	 * @throws \Throwable
	 */
	public function fetch($template, array $data = []) {
	
		$bt = debug_backtrace();
		
		//$this->used_templates[] = $template.'|'.$bt[0]['file'].'|'.$bt[0]['line'];
		
		$this->used_templates[$bt[0]['file'].'|'.$bt[0]['line']] = $template;
		if (isset($data['template'])) {
			throw new \InvalidArgumentException("Duplicate template key found");
		}

		$real_template_path = $this->templatePath;
		if (in_array($template[0], array(DIRECTORY_SEPARATOR, '\\', '/'))) {
			$tmp = str_replace(array('\\', '/'), array(DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR), $this->templatePath);
			$tmp = rtrim($tmp.DIRECTORY_SEPARATOR, '\\/');
			$tmp_array = explode(DIRECTORY_SEPARATOR, $tmp);
			array_pop($tmp_array);
			$real_template_path = implode(DIRECTORY_SEPARATOR, $tmp_array);

		} else {
			$real_template_path = '';
		}

		if (!is_file($real_template_path.$template)) {
			dve("View cannot render `".$real_template_path."|||".$template."` because the template does not exist");
			throw new \RuntimeException("View cannot render `".$real_template_path."$template` because the template does not exist");
		}


		/*
		foreach ($data as $k=>$val) {
			if (in_array($k, array_keys($this->attributes))) {
				throw new \InvalidArgumentException("Duplicate key found in data and renderer attributes. " . $k);
			}
		}
		*/
		$data = array_merge($this->attributes, $data);

		try {
			ob_start();
			$this->protectedIncludeScope($real_template_path . $template, $data);
			$output = ob_get_clean();

			if (
				!empty($_COOKIE['templateHelper']) && $_COOKIE['templateHelper'] == 1
				&&
				(
					empty($_SERVER['HTTP_X_REQUESTED_WITH'])
					||
					strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest'
				)
				&&
				(!strstr($template, 'layout'.DIRECTORY_SEPARATOR.'body'))
			){
				$first_tag = '';
				if(preg_match('(<(\w+)[^>]*>)', $output, $matches)){
					if(strstr($matches[0],'class="')){
						$first_tag = str_replace('class="', 'data-template="'.$template.'" class="templateInfo ', $matches[0]);
					} else {
						$first_tag = str_replace('<'.$matches[1], '<'.$matches[1].' data-template="'.$template.'" class="templateInfo" ', $matches[0]);
					}
					$output = str_replace($matches[0], $first_tag, $output);
				} else {
					$output = '<span data-template="'.$template.'" class="templateInfo">'.$output.'</span>';
				}

				$output = '<!--START '.$this->templatePath.$template.'-->'.$output.'<!-- '.$template.' END-->';
			}

		} catch(\Throwable $e) { // PHP 7+
			ob_end_clean();
			throw $e;
		} catch(\Exception $e) { // PHP < 7
			ob_end_clean();
			throw $e;
		}




		return $output;
	}

	/**
	 * @param string $template
	 * @param array $data
	 */
	protected function protectedIncludeScope ($template, array $data) {
		extract($data);
        include func_get_arg(0);
	}
}
