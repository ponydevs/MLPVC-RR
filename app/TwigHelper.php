<?php

declare(strict_types=1);

namespace App;

use App\Models\LinkableInterface;

class TwigHelper {
	/** @var \Twig_Environment */
	public static $env;

	public static function init(\Twig_LoaderInterface $loader, array $options = []):void {
		self::$env = new \Twig_Environment($loader, $options);
		self::$env->addFunction(new \Twig_SimpleFunction('permission', function (string $role, ?string $compare_against = null){
			return Permission::sufficient($role, $compare_against);
		}));
		self::$env->addFunction(new \Twig_SimpleFunction('url', function (LinkableInterface $linkable){
			return $linkable->toURL();
		}));
		self::$env->addFunction(new \Twig_SimpleFunction('export_vars', function (array $variables){
			return CoreUtils::exportVars($variables);
		}));
		self::$env->addFunction(new \Twig_SimpleFunction('user_pref', function (...$args){
			return UserPrefs::get(...$args);
		}));
		self::$env->addFunction(new \Twig_SimpleFunction('global_setting', function (...$args){
			return GlobalSettings::get(...$args);
		}));
		self::$env->addFunction(new \Twig_SimpleFunction('posess', function (...$args){
			return CoreUtils::posess(...$args);
		}));
		self::$env->addFunction(new \Twig_SimpleFunction('time_tag', function (...$args){
			return Time::tag(...$args);
		}));
		self::$env->addFunction(new \Twig_SimpleFunction('make_plural', function (...$args){
			return CoreUtils::makePlural(...$args);
		}));
		self::$env->addFunction(new \Twig_SimpleFunction('cached_asset_link', function (...$args){
			return CoreUtils::cachedAssetLink(...$args);
		}));
		self::$env->addFunction(new \Twig_SimpleFunction('cutoff', function (...$args){
			return CoreUtils::cutoff(...$args);
		}));

		self::$env->addFilter(new \Twig_SimpleFilter('apos_encode', function (string $value){
			return CoreUtils::aposEncode($value);
		}));
	}

	public static function display(string $view, array $data = [], string $extension = 'html.twig'):void {
		header('Content-Type: text/html; charset=utf-8;');
		/** @noinspection PhpTemplateMissingInspection */
		echo self::$env->render("$view.$extension", $data);
		exit;
	}
}
