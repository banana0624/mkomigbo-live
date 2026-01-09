<?php
declare(strict_types=1);

// /private/functions/sanitize.php

function mk_sanitize_allowlist_html(string $html): string {
  $html = trim($html);
  if ($html === '') return '';

  $allowed_tags = [
    'p','br','hr',
    'strong','b','em','i','u',
    'ul','ol','li',
    'blockquote',
    'h2','h3','h4',
    'code','pre',
    'a',
    'span',
  ];

  $allowed_attrs = [
    'a'    => ['href','title','rel','target'],
    'span' => ['class'],
    '*'    => [],
  ];

  $dom = new DOMDocument();
  libxml_use_internal_errors(true);
  $dom->loadHTML('<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
  libxml_clear_errors();

  $root = $dom->getElementsByTagName('div')->item(0);
  if (!$root) return '';

  $walk = function (DOMNode $node) use (&$walk, $allowed_tags, $allowed_attrs): void {
    if ($node->nodeType === XML_ELEMENT_NODE) {
      $tag = strtolower($node->nodeName);

      if (!in_array($tag, $allowed_tags, true)) {
        $parent = $node->parentNode;
        if ($parent) {
          while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
          }
          $parent->removeChild($node);
        }
        return;
      }

      // Attribute filtering
      if ($node->hasAttributes()) {
        $keep = $allowed_attrs[$tag] ?? ($allowed_attrs['*'] ?? []);
        $toRemove = [];
        foreach (iterator_to_array($node->attributes) as $attr) {
          $name = strtolower($attr->name);

          if (str_starts_with($name, 'on') || $name === 'style') {
            $toRemove[] = $attr->name;
            continue;
          }
          if (!in_array($name, $keep, true)) {
            $toRemove[] = $attr->name;
          }
        }
        foreach ($toRemove as $rm) $node->removeAttribute($rm);
      }

      // Anchor hardening: allow only relative-path (single slash) or http(s)
      if ($tag === 'a') {
        $href = trim((string)$node->getAttribute('href'));

        // normalize common obfuscation (control chars)
        $href = preg_replace('/[\x00-\x1F\x7F]/', '', $href) ?? $href;

        $ok = false;
        if ($href !== '') {
          if ($href[0] === '/') {
            // reject protocol-relative //example.com
            $ok = !preg_match('~^//~', $href);
          } elseif (preg_match('~^https?://~i', $href)) {
            $ok = true;
          }
        }

        if (!$ok) {
          $node->removeAttribute('href');
          $node->removeAttribute('target');
          $node->removeAttribute('rel');
        } else {
          $node->setAttribute('rel', 'noopener noreferrer');
          $node->setAttribute('target', '_blank');
        }
      }
    }

    $children = [];
    foreach ($node->childNodes as $c) $children[] = $c;
    foreach ($children as $c) $walk($c);
  };

  $walk($root);

  $out = '';
  foreach ($root->childNodes as $child) {
    $out .= $dom->saveHTML($child);
  }
  return trim($out);
}
