<?php

declare(strict_types=1);

namespace App;

use App\Models\Linkable;
use SeinopSys\RGBAColor;

class Twig {
	/** @var \Twig_Environment */
	public static $env;

	public static function init(\Twig_LoaderInterface $loader, array $options = []):void {
		self::$env = new \Twig_Environment($loader, $options);
		self::$env->addFunction(new \Twig_SimpleFunction('permission', '\App\Permission::sufficient'));
		self::$env->addFunction(new \Twig_SimpleFunction('export_vars', '\App\CoreUtils::exportVars'));
		self::$env->addFunction(new \Twig_SimpleFunction('user_pref', '\App\UserPrefs::get'));
		self::$env->addFunction(new \Twig_SimpleFunction('global_setting', '\App\GlobalSettings::get'));
		self::$env->addFunction(new \Twig_SimpleFunction('posess', '\App\CoreUtils::posess'));
		self::$env->addFunction(new \Twig_SimpleFunction('time_tag', '\App\Time::tag'));
		self::$env->addFunction(new \Twig_SimpleFunction('make_plural', '\App\CoreUtils::makePlural'));
		self::$env->addFunction(new \Twig_SimpleFunction('cached_asset_link', '\App\CoreUtils::cachedAssetLink'));
		self::$env->addFunction(new \Twig_SimpleFunction('cutoff', '\App\CoreUtils::cutoff'));
		self::$env->addFunction(new \Twig_SimpleFunction('sd', '\sd'));
		self::$env->addFunction(new \Twig_SimpleFunction('env', '\App\CoreUtils::env'));
		self::$env->addFunction(new \Twig_SimpleFunction('setting_form', function (...$args){
			return (new UserSettingForm(...$args))->render();
		}));
		self::$env->addFunction(new \Twig_SimpleFunction('url', function (Linkable $linkable){
			return $linkable->toURL();
		}));
		self::$env->addFunction(new \Twig_SimpleFunction('hex2rgb', function (string $color){
			return RGBAColor::parse($color)->toRGB();
		}));

		self::$env->addFilter(new \Twig_SimpleFilter('apos_encode', '\App\CoreUtils::aposEncode'));

		self::$env->addTest(new \Twig_Test('numeric', 'is_numeric'));
	}

	public static function display(string $view, array $data = []):void {
		header('Content-Type: text/html; charset=utf-8;');
		/** @noinspection PhpTemplateMissingInspection */
		echo self::$env->render("$view.html.twig", $data);
		exit;
	}
}
