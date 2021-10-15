<?php
/**
 * Slim Framework (http://slimframework.com)
 *
 * @link      https://github.com/slimphp/PHP-View
 * @copyright Copyright (c) 2011-2015 Josh Lockhart
 * @license   https://github.com/slimphp/PHP-View/blob/master/LICENSE.md (MIT License)
 */
namespace Egonom\PhpRenderer;

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
		if (!empty($this->getAttribute('lo_css'))) {
			if(isDev()){
				foreach ($this->getAttribute('lo_css') AS $css => $where) {
					$this->used_templates[$css] = '<a href="phpstorm://open?url=file://'.DEFAULT_LOCAL_WEBROOT.'\\public\\' . $css . '&line=1">CSS: ' . $css . '</a>';
				}
			}
			$this->compactCss();
		}
		if (!empty($this->getAttribute('lo_js'))) {
			if(isDev()){
				foreach ($this->getAttribute('lo_js') AS $js) {
					$this->used_templates[$js] = '<a href="phpstorm://open?url=file://'.DEFAULT_LOCAL_WEBROOT.'\\public\\' . $js . '&line=1">JS: ' . $js . '</a>';
				}
			}
			$this->compactJs();
		}

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
			$this->used_templates = array_reverse($this->used_templates);
			$output .= '<style>#renderInfo{border-top:3px solid red;width: 100vw; height: 32px;overflow: scroll;position: fixed; left: 0px; bottom: 0px; background-color: #ccc; z-index: 10000;}#renderInfo.on{height: 500px;}</style>';
			$output .= '<div id="renderInfo"><script type="application/javascript">
													$(document).ready(function () {
														$("#renderInfo").on("click", function(){
															$(this).toggleClass("on");
														});
													});</script>
						
			';
			foreach($this->used_templates AS $link){
				$output .= $link.'<br>';
			}

			$output .= rv('', '', 4);
			$output .= '</div>';
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
		$this->templatePath = rtrim($templatePath, '/\\') . DIRECTORY_SEPARATOR;
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
	public function fetch($template, array $data = [], $do_not_use_templatehelper = false) {
		if(!defined('DEFAULT_LOCAL_WEBROOT')) {
			define('DEFAULT_LOCAL_WEBROOT', realpath(getcwd().DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR));
		}

		$real_template_path = '';

		$param_template = $template;

		$this->templatePath = str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $this->templatePath);
		$template = str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $template);

		//ha abszolút path van megadva
		if (!is_file(realpath($template))) {
			$real_path = $this->templatePath.$template;
			$template = realpath($real_path);
		}

		if (
			!empty($_COOKIE['templateHelper']) && $_COOKIE['templateHelper'] == 1
			&&
			(
				empty($_SERVER['HTTP_X_REQUESTED_WITH'])
				||
				strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest'
			)
		) {
			$bt = debug_backtrace();


			$template_path = str_replace(realpath(getcwd().'/..'), '', $template);

			$this->used_templates[$bt[0]['file'] . '|' . $bt[0]['line']] = '<a href="phpstorm://open?url=file://' .DEFAULT_LOCAL_WEBROOT. $template_path . '&line=1">TEMPLATE: ' . $template . '</a>';
		}
		if (isset($data['template'])) {
			throw new \InvalidArgumentException("Duplicate template key found");
		}

		if (!is_file($real_template_path.$template)) {
			dv(array(
				'$this->templatePath' => $this->templatePath,
				'$param_template' => $param_template,
				'$real_path' => $real_path,
				'__DIR__' => __DIR__,
				'getcwd()' => getcwd(),
				'$template' => $template,
				'$real_template_path' => $real_template_path,
			));

			throw new \RuntimeException("View cannot render `".$real_path.' | '.$real_template_path."$template` because the template does not exist");
		}

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
				$do_not_use_templatehelper === false
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

	function compactCss(){
		$groups = array();
		$tmp_array = array();
		foreach ($this->getAttribute('lo_css') AS $css => $where){
			if (!empty($groups[$where])) {
				$groups[$where][] = $css;
			} else {
				$groups[$where] = array($css);
			}
		}

		foreach($groups AS $where => $css_s){
			$index = md5(implode('', $css_s).$where.CSS_VERSION);
			$css_cache_path = getcwd().'/_cache/'.$index;

			if(is_file($css_cache_path)){
				$tmp_array['/_cache/'.$index] = $where;
			} else {
				$fp1 = fopen($css_cache_path, 'a+');
				foreach($css_s AS $path){

					unset($this->attributes['lo_css'][$path]);
					if(substr(strtolower($path), 0, 2) == '//'){
						$path = 'https:'.$path;
					}

					$path = strstr(strtolower($path), 'http') ? $path : (getcwd().$path);
					$file2 = " \n ".str_replace(array("\n", "\r"), ' ', file_get_contents($path));
					fwrite($fp1, $file2);
				}
				fclose($fp1);
				$tmp_array['/_cache/'.$index] = $where;

			}

		}
		$this->attributes['lo_css'] = $tmp_array;
//dv($this->getAttribute('lo_css'));
	}
	function compactJs(){
		$tmp_array = array();

		$index = md5(implode('', array_values($this->getAttribute('lo_js')) ).CSS_VERSION);//.date('is');
		$js_cache_path = getcwd().'/_cache/'.$index;

		$tmp_array['/_cache/'.$index] = '/_cache/'.$index;
		if(!is_file($js_cache_path)){


			$mem_limit = ini_get('memory_limit');
			ini_set('display_errors', false);
			ini_set('memory_limit', '1G');

			$fp1 = fopen($js_cache_path, 'a+');
			foreach (array_values($this->getAttribute('lo_js')) AS $index2 => $path){
				if(substr(strtolower($path), 0, 2) == '//'){
					$path = 'https:'.$path;
				}
				$path = strstr(strtolower($path), 'http') ? $path : (getcwd().$path);

				$file2 = " \n ".file_get_contents($path);
				$file2 = preg_replace('/^[\t\s]+(.*)$/m', ' $1 ', $file2);
				$file2 = preg_replace('/(^\/\/.*)$/m', ' ', $file2);

				$file2 = $this->cleanRN($file2);

				fwrite($fp1, " \n ".$file2." \n ");

			}
			fclose($fp1);
			$tmp_array['/_cache/'.$index] = '/_cache/'.$index;

			ini_set('memory_limit', $mem_limit);

		}
		$this->attributes['lo_js'] = $tmp_array;
	}

	function cleanRN($file2){
		$clean = '';
		$matches = explode("\r", $file2);
		if(count($matches) > 3) {
			foreach($matches AS $line){
				if(strstr($line, '//')) {
					$line = $line."\r";
				}
				$clean .= (' '.$line.' ');
			}
			$file2 = $clean;
		}

		$clean = '';
		$matches = explode("\n", $file2);
		if(count($matches) > 3) {
			foreach($matches AS $line){
				if(strstr($line, '//')) {
					$line = $line."\n";
				}
				$clean .= (' '.$line.' ');
			}
			$file2 = $clean;
		}

		return $file2;
	}
}
