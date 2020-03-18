<?php

declare(strict_types=1);

namespace App;

use App\Models\Linkable;
use SeinopSys\RGBAColor;
use Twig\Environment;
use Twig\Loader\LoaderInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

class Twig {
  /** @var Environment */
  public static $env;

  public static function init(LoaderInterface $loader, array $options = []):void {
    self::$env = new Environment($loader, $options);
    self::$env->addFunction(new TwigFunction('permission', '\App\Permission::sufficient'));
    self::$env->addFunction(new TwigFunction('export_vars', '\App\CoreUtils::exportVars'));
    self::$env->addFunction(new TwigFunction('user_pref', '\App\UserPrefs::get'));
    self::$env->addFunction(new TwigFunction('global_setting', '\App\GlobalSettings::get'));
    self::$env->addFunction(new TwigFunction('posess', '\App\CoreUtils::posess'));
    self::$env->addFunction(new TwigFunction('time_tag', '\App\Time::tag'));
    self::$env->addFunction(new TwigFunction('make_plural', '\App\CoreUtils::makePlural'));
    self::$env->addFunction(new TwigFunction('cached_asset_link', '\App\CoreUtils::cachedAssetLink'));
    self::$env->addFunction(new TwigFunction('cutoff', '\App\CoreUtils::cutoff'));
    self::$env->addFunction(new TwigFunction('sd', '\sd'));
    self::$env->addFunction(new TwigFunction('env', '\App\CoreUtils::env'));
    self::$env->addFunction(new TwigFunction('setting_form', fn(...$args) => (new UserSettingForm(...$args))->render()));
    self::$env->addFunction(new TwigFunction('url', fn(Linkable $linkable) => $linkable->toURL()));
    self::$env->addFunction(new TwigFunction('hex2rgb', fn(string $color) => RGBAColor::parse($color)->toRGB()));

    self::$env->addFilter(new TwigFilter('apos_encode', '\App\CoreUtils::aposEncode'));
    self::$env->addFilter(new TwigFilter('wbr_slash', fn(string $text) => preg_replace('~/([^/])~', '/<wbr>$1', $text)));

    self::$env->addTest(new TwigTest('numeric', 'is_numeric'));
  }

  public static function display(string $view, array $data = []):void {
    header('Content-Type: text/html; charset=utf-8;');
    /** @noinspection PhpTemplateMissingInspection */
    echo self::$env->render("$view.html.twig", $data);
    exit;
  }
}
