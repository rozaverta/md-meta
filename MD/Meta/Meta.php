<?php
/**
 * Created by IntelliJ IDEA.
 * User: GoshaV [Maniako] <gosha@rozaverta.com>
 * Date: 10.09.2017
 * Time: 16:23
 */

namespace MD\Meta;

use EApp\App;
use EApp\Support\Str;

class Meta
{
	protected $name = 'meta';

	protected $ignore = [];

	protected $ignoreDefault = false;

	protected $fill = [];

	protected $default = [];

	protected $meta = null;

	protected static $meta_alias =
		[
			'shortcut icon' => 'icon',
			'favicon' => 'icon'
		];

	protected static $icon_types =
		[
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'ico'  => 'image/x-icon',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
		];

	public function __construct( array $fill = [], $name = null )
	{
		$this->fill = $fill;

		if( !is_null($name) )
		{
			$this->name = (string) $name;
		}

		$this->default = App::getInstance()->config('meta');
	}

	public function ignoreDefault()
	{
		$this->ignoreDefault = true;
	}

	public function ignore( ... $args )
	{
		if( count($args) == 1 && is_array($args[0]))
		{
			$args = $args[0];
		}

		$this->ignore = $args;
		return $this;
	}

	public function fill( array & $data )
	{
		if( isset($data[$this->name]) )
		{
			return $this;
		}

		$data[$this->name] = [];
		$this->meta = & $data[$this->name];
		$fill = [];

		foreach($this->fill as $name => $value)
		{
			if( ! is_int($name) )
			{
				$fill[$name] = $value;
			}
			else if( $value !== $this->name && isset($data[$value]) )
			{
				$this->create($value, $data[$value]);
				unset($data[$value]);
			}
		}

		// title

		if( isset($fill['title']) )
		{
			$this->create('title', $fill['title']);
			unset($fill['title']);
		}
		else if( $this->create('title', isset($data["title"]) ? $data["title"] : ( $data["page_title"] ? $data["page_title"] : '' )) )
		{
			$keys[] = 'title';
		}

		// keywords, description, image

		$keys = ['keywords', 'description', 'image'];
		foreach($keys as $key)
		{
			if( isset($fill[$key]) )
			{
				$this->create($key, $fill[$key]);
				unset($fill[$key]);
				continue;
			}

			if( isset($data[$key]) && $this->create($key, $data[$key]) !== false )
			{
				unset($data[$key]);
				continue;
			}

			if( !$this->ignoreDefault && isset($this->default[$key]) )
			{
				$this->create($key, $this->default[$key]);
			}

			unset($data[$key]);
		}

		// other fill
		foreach( $fill as $name => $value )
		{
			$key = $this->create($name, $value);
			if( $key ) {
				$fill[] = $key;
			}
		}

		// default
		if( !$this->ignoreDefault )
			foreach( $this->default as $name => $value )
			{
				if( !in_array($name, $keys) )
				{
					$this->create($name, $value);
				}
			}

		unset( $this->meta );
		return $this;
	}

	protected function create( $name, $value )
	{
		$lower = Str::lower($name);
		if( isset(self::$meta_alias[$lower]) )
		{
			$name = self::$meta_alias[$lower];
		}

		// ignore user

		if( in_array($lower, $this->ignore, true) )
		{
			return false;
		}

		// ignore empty data

		if( ! is_array($value) )
		{
			$value = trim($value);
			if( !strlen($value) )
			{
				return false;
			}
		}
		else if( !count($value) )
		{
			return false;
		}

		// load

		if( $lower == 'keywords' )
		{
			$this->addName($lower, $value);
		}

		else if( $lower == 'description' )
		{
			$this->addName($lower, $value);
			$this->addName('twitter:description', $value);
			$this->addProperty('og:description', $value);
		}

		else if( $lower == 'title' )
		{
			$this->addName('twitter:title', $value);
			$this->addProperty('og:title', $value);
		}

		else if( $lower == 'type' )
		{
			$this->addProperty('og:type', $value);
		}

		else if( $lower == 'url' )
		{
			if( substr($value, 0, 2) === '//' )
			{
				$value = 'http:' . $value;
			}
			else if( strpos($value, '://') === false )
			{
				$value = BASE_PROTOCOL . '://' . APP_HOST . ltrim($value, '/');
			}

			$this->addProperty('og:url', $value);
		}

		else if( $lower == 'image' )
		{
			$this->addImage($value);
		}

		else if( $lower == 'link' )
		{
			if( !is_array($value) )
			{
				$value = ["href" => $value];
			}
			else if( ! isset($value['href']) )
			{
				return false;
			}

			$this->addLink($value);
		}

		else if( $lower == 'icon' || preg_match('/(^icon-|-icon$)/', $lower) )
		{
			if( !is_array($value) )
			{
				$value = ["href" => $value];
			}
			else if( ! isset($value['href']) )
			{
				return false;
			}

			$image = $this->getImage($value['href']);
			if( !isset($value['size']) && $image['width'] > 0 )
			{
				$value['size'] = $image['width'] . 'x' . $image['height'];
			}

			if( !isset($value['type']) && preg_match('/\.(ico|jpg|jpeg|png|gif)(?:$|\?)/i', $image['src'], $m) )
			{
				$type = strtolower($m[1]);
				$value['type'] = self::$icon_types[$type];
			}

			$value['href'] = $image['src'];
			$value['rel'] = $lower;

			$this->addLink($value);
		}

		else if( preg_match('/^(name|property):(.*?)$/i', $name, $m) )
		{
			if( $m[1] == 'name' )
			{
				$this->addName($m[2], $value);
			}
			else
			{
				$this->addProperty($m[2], $value);
			}
		}

		else
		{
			return false;
		}

		return $lower;
	}

	protected function addName( $name, $value )
	{
		$this->addNameProperty('name', $name, $value);
	}

	protected function addProperty( $name, $value )
	{
		$this->addNameProperty('property', $name, $value);
	}

	protected function addNameProperty( $type, $name, $value )
	{
		if( is_string($value) )
		{
			$value = strip_tags($value);
			$value = preg_replace('/\s+/', ' ', $value);
			$value = html_entity_decode($value);

			$this->meta[] =
				[
					"meta_type" => 'meta',
					$type => $name,
					"content" => $value
				];
		}
	}

	protected function addLink( array $data )
	{
		$data['meta_type'] = 'link';

		if( !isset($data['type']) && preg_match('/\.css($|\?)/', $data['href']) )
		{
			$data['type'] = 'text/css';
			if( !isset($data['rel']) )
			{
				$data['rel'] = "stylesheet";
			}
		}

		$this->meta[] = $data;
	}

	protected function addImage( $src )
	{
		if( !is_string($src) )
		{
			return;
		}

		$image = $this->getImage($src);

		$this->addProperty('og:image', $image["src"]);

		// add og- size
		if( $image["width"] > 0 && $image["height"] > 0 )
		{
			$this->addProperty('og:image:width', (string) $image["width"]);
			$this->addProperty('og:image:height', (string) $image["height"]);
		}

		$this->addName('twitter:image', $image["src"]);

		if( $image["width"] >= 600 && ! isset($this->fill['name:twitter:card']) )
		{
			$this->addName('twitter:card', 'summary_large_image');
		}
	}

	protected function getImage( $src )
	{
		$size = false;
		$image =
			[
				"width" => 0,
				"height" => 0
			];

		if( substr($src, 0, 2) === '//' )
		{
			$src = 'http:' . $src;
		}

		if( strpos( $src, '://' ) === false )
		{
			$src = ltrim($src, '/');
			$path = $src;
			$src = BASE_PROTOCOL . '://' . APP_HOST . '/' . $src;

			if( DIRECTORY_SEPARATOR !== '/' )
			{
				$path = str_replace('/', DIRECTORY_SEPARATOR, $path);
			}
			$path = BASE_DIR . $path;
			if( file_exists($path) )
			{
				$size = @ getimagesize($path);
			}
		}
		else
		{
			$size = @ getimagesize($src);
		}

		if( $size )
		{
			$image["width"] = $size[0];
			$image["height"] = $size[1];
		}

		$image["src"] = $src;

		return $image;
	}
}