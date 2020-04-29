<?php

namespace App;

use RuntimeException;

class LibHelper {
  public const TYPE_JS = 0b01;
  public const TYPE_CSS = 0b10;
  public const TYPE_JS_CSS = 0b11;

  public const AVAILABLE_LIBS = [
    'autocomplete' => self::TYPE_JS,
    'ba-throttle-debounce' => self::TYPE_JS,
    'blob' => self::TYPE_JS,
    'canvas-to-blob' => self::TYPE_JS,
    'codemirror' => self::TYPE_JS_CSS,
    'cuid' => self::TYPE_JS,
    'dragscroll' => self::TYPE_JS,
    'file-saver' => self::TYPE_JS,
    'fluidbox' => self::TYPE_JS_CSS,
    'font-awesome' => self::TYPE_CSS,
    'inert' => self::TYPE_JS,
    'jquery' => self::TYPE_JS,
    'md5' => self::TYPE_JS,
    'moment' => self::TYPE_JS,
    'no-ui-slider' => self::TYPE_JS_CSS,
    'paste' => self::TYPE_JS,
    'polyfill-io' => self::TYPE_JS,
    'react' => self::TYPE_JS,
    'sortable' => self::TYPE_JS,
    'typicons' => self::TYPE_CSS,
  ];

  /**
   * @param string $lib
   *
   * @return array
   * @throws RuntimeException
   */
  public static function get(string $lib):array {
    if (!isset(self::AVAILABLE_LIBS[$lib]))
      throw new RuntimeException("Library $lib not found in ".__CLASS__.'::AVAILABLE_LIBS');
    $lib_value = self::AVAILABLE_LIBS[$lib];
    $js = ($lib_value & self::TYPE_JS) !== 0;
    $css = ($lib_value & self::TYPE_CSS) !== 0;

    return [$js, $css];
  }

  /**
   * @param array    $scope
   * @param array    $options
   * @param string[] $defaults
   */
  public static function process(&$scope, $options, $defaults):void {
    $js_libs = [];
    $css_libs = [];
    $libs = isset($options['default-libs']) && $options['default-libs'] === false ? [] : $defaults;
    if (!empty($options['libs'])){
      $libs = array_unique(array_merge($libs, $options['libs']));
    }
    foreach ($libs as $lib){
      [$js, $css] = self::get($lib);
      if ($js){
        /** @noinspection PhpTemplateMissingInspection */
        $js_libs[] = Twig::$env->render("layout/js_libs/_$lib.html.twig");
      }
      if ($css){
        /** @noinspection PhpTemplateMissingInspection */
        $css_libs[] = Twig::$env->render("layout/css_libs/_$lib.html.twig");
      }
    }
    if (!empty($js_libs))
      $scope['js_libs'] = implode('', $js_libs);
    if (!empty($css_libs))
      $scope['css_libs'] = implode('', $css_libs);
  }
}
