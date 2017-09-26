<?php

namespace MD\Meta\Plugin;

use EApp\App;
use EApp\Proto\Plugin;

class HtmlMetaTags extends Plugin
{
	public function getContent()
	{
		if( $this->isArray('meta') )
		{
			$data = $this->get('meta');
		}
		else
		{
			$name = $this->getOr('name', 'meta');
			$view = App::View();
			if( $view->isArray($name) )
			{
				$data = $view->get($name);
			}
			else
			{
				return '';
			}
		}

		if( !count($data) )
		{
			return '';
		}

		$get = '';
		$nl = $this->getOr("nl", "\n");

		foreach( $data as $item )
		{
			$get .= $this->getItem($item) . $nl;
		}

		return $get;
	}

	protected function getItem( $prop )
	{
		$tag = 'meta';
		$out = '';

		foreach( $prop as $name => $value )
		{
			if( $name == 'meta_type' )
			{
				$tag = $value;
			}
			else
			{
				$out .= ' ' . $name . '="' . htmlspecialchars($value) . '"';
			}
		}

		return '<' . $tag . $out . ' />';
	}
}