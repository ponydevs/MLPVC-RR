<?php

namespace App;

use function is_string;
use function mb_substr;

/**
 * A class for recursively storing navigation breadcrumbs
 *
 * @see View::getBreadcrumb For rendering implementation
 */
class NavBreadcrumb {
  /** @var string */
  private $name;

  /** @var string|null */
  private $link;

  /** @var bool */
  private $active, $enabled;

  /** @var NavBreadcrumb */
  private $child;

  public function __construct(string $name, ?string $link = null, bool $active = false) {
    $this->name = $name;
    $this->link = $link;
    $this->enabled = $link !== null;
    $this->active = $active;
  }

  public function setLink(?string $link, bool $enable = true) {
    $this->link = $link;
    if ($enable)
      $this->enabled = true;

    return $this;
  }

  public function setActive(bool $value = true) {
    $this->active = $value;

    return $this;
  }

  public function setEnabled(bool $value) {
    $this->enabled = $value;

    return $this;
  }

  /**
   * @param string|NavBreadcrumb $ch
   * @param bool                 $activate Whether to set child as active
   *
   * @return self
   */
  public function setChild($ch, bool $activate = false):NavBreadcrumb {
    if (is_string($ch)){
      $ch = new self($ch);
      $activate = true;
    }
    if ($activate === true)
      $ch->setActive();
    $this->child = $ch;

    return $this;
  }

  /**
   * Returns the last breadcrumb in the chain to make appending easier
   *
   * @return self
   */
  public function end():NavBreadcrumb {
    $end = $this;
    while ($end->child !== null){
      $end = $end->child;
    }

    return $end;
  }

  public function getChild():?NavBreadcrumb {
    return $this->child;
  }

  public function toAnchor(int $position) {
    $extra_attributes = 'itemscope itemtype="http://schema.org/Thing" itemprop="item"';
    if ($this->link){
      $itemid = ABSPATH.mb_substr($this->link, 1);
    } else {
      $itemid = md5($this->name);
    }
    if ($itemid) {
      $extra_attributes .= sprintf(" itemid='%s'", CoreUtils::aposEncode($itemid));
    }
    $name = '<span itemprop="name">'.CoreUtils::escapeHTML($this->name).'</span>';

    $HTML = $this->active
      ? "<strong $extra_attributes>$name</strong>"
      : (
      $this->enabled
        ? "<a href='{$this->link}' $extra_attributes>$name</a>"
        : "<span $extra_attributes>$name</span>"
      );

    return "<li itemprop='itemListElement' itemscope itemtype='http://schema.org/ListItem'>$HTML<meta itemprop='position' content='$position'></li>";
  }

  public const DIV = '<li class="div">/</li>';

  public function __toString():string {
    $position = 1;
    $HTML = [];
    $ptr = $this;
    do {
      $HTML[] = $ptr->toAnchor($position);
      $ptr = $ptr->getChild();
      $position++;
    } while ($ptr !== null);

    return self::DIV.implode(self::DIV, $HTML);
  }
}
