<?php
// project-root/private/registry/seo_templates.php

// Central templates for SEO (edit in one place)
return [
  'site' => [
    'title'       => 'Mkomigbo • Igbo Heritage Resource Center',
    'description' => 'Explore 19 subjects covering Igbo history, culture, language, people, and more.',
    'keywords'    => 'igbo, culture, history, language, people, biafra, africa',
    'site_name'   => 'Mkomigbo',
    'separator'   => ' • ',
  ],
  'subject' => [
    'title'       => '{subject_name}{separator}{site_name}',
    'description' => '{subject_meta_description|site_description}',
    'keywords'    => '{subject_meta_keywords|site_keywords}',
  ],
  'page' => [
    'title'       => '{page_title}{separator}{site_name}',
    'description' => '{page_meta_description|subject_meta_description|site_description}',
    'keywords'    => '{page_meta_keywords|subject_meta_keywords|site_keywords}',
  ],
  'platform' => [
    'title'       => '{platform_name}{separator}{site_name}',
    'description' => '{platform_description|site_description}',
    'keywords'    => '{platform_keywords|site_keywords}',
  ],
  'contributor' => [
    'title'       => '{contributor_name}{separator}{site_name}',
    'description' => '{contributor_bio|site_description}',
    'keywords'    => '{contributor_keywords|site_keywords}',
  ],
];
