<?php
declare(strict_types=1);
echo '<div class="mk-prose">';
echo '<p class="mk-muted" style="margin-top:0;">A comprehensive guide to world religions — ancient and living, Eastern and Western, African and global — with founders, beliefs, doctrines, dogma, proselytism, apostasy, life-cycle traditions, metempsychosis, reincarnation, and karma.</p>';
echo '<h2>About This Subject</h2>';
echo '<p>Religion is among the most consequential forces in human history. This subject covers the full spectrum of human religious experience — from the oldest known traditions to the newest movements — treating each with equal respect and equal scrutiny. Every major tradition is documented at thesis level: what it believes, how it defines faith, what it requires of adherents, what happens to those who leave, how it marks birth, marriage, and death, and how it understands the soul\'s fate after death.</p>';
echo '<h2>Navigate the Subject</h2>';
echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;margin:16px 0;">';
$sections = [
  ['/subjects/religion/overview/','🗺️ Full Map of World Religions','All traditions listed — ancient, living, Eastern, Western, African, modern'],
  ['/subjects/religion/ancient_doctrine/','📋 Ancient Doctrines','Kemet, Mesopotamia, Maya, Inca, Norse, Celtic — faith, birth, marriage, death, metempsychosis'],
  ['/subjects/religion/topics/','🏛️ Ancient Religions','Kemet, Mesopotamia, Maya, Inca, Norse, Druid — beliefs, deities, practices'],
  ['/subjects/religion/eastern/','☸️ Eastern Religions','Hinduism, Buddhism, Sikhism, Confucianism, Taoism, Shinto, Zoroastrianism'],
  ['/subjects/religion/eastern_doctrine/','📋 Eastern Doctrines','Faith, dogma, proselytism, apostasy, birth, marriage, death — Hinduism, Buddhism, Sikhism, Zoroastrianism'],
  ['/subjects/religion/abrahamic/','✡️ Abrahamic Religions','Judaism, Christianity, Islam — all branches, founders, beliefs, practices'],
  ['/subjects/religion/african_doctrine/','📋 African Doctrines','Ọdinala, Yoruba, Akan, Zulu — faith, birth, marriage, death, metempsychosis'],
  ['/subjects/religion/african/','🌍 African Religions','Ọdinala, Yoruba/Ifá, Akan, Zulu, Kongo, Vodou, and diaspora traditions'],
  ['/subjects/religion/modern_doctrine/','📋 Modern Doctrines','Baháʼí, Eckankar, Rastafari, Mormonism, Wicca — faith, birth, marriage, death, reincarnation'],
  ['/subjects/religion/modern/','✨ Modern Religions','Baháʼí, Eckankar, Rastafari, Cao Dai, Spiritism, Wicca, Mormonism'],
  ['/subjects/religion/doctrine/','📋 Doctrine, Dogma & Life Cycle','Faith, proselytism, apostasy, birth, marriage, and death across all traditions'],
  ['/subjects/religion/metempsychosis/','♾️ Metempsychosis & Karma','Reincarnation variants, karma doctrines — Igbo vs Hindu vs Buddhist vs Abrahamic'],
  ['/subjects/religion/people/','👤 Founders & Teachers','Prophets, founders, reformers, and teachers across all traditions'],
  ['/subjects/religion/sources/','📚 Sacred Texts & Downloads','Core scriptures with free download links'],
];
foreach($sections as [$href,$title,$desc]) {
  echo '<a href="'.$href.'" style="display:block;padding:14px;border:1px solid #e5e7eb;border-radius:12px;text-decoration:none;color:inherit;background:#fff;transition:box-shadow .12s;" onmouseover="this.style.boxShadow=\'0 4px 16px rgba(0,0,0,.09)\'" onmouseout="this.style.boxShadow=\'\'">';
  echo '<div style="font-weight:800;font-size:.95rem;color:#111;margin-bottom:4px;">'.$title.'</div>';
  echo '<div style="font-size:.82rem;color:#6b7280;line-height:1.4;">'.$desc.'</div>';
  echo '</a>';
}
echo '</div>';
echo '<h2>Our Approach</h2>';
echo '<p>All traditions are presented with equal respect and equal scrutiny. We do not rank religions or declare any tradition true or false. We document what traditions teach, what they practice, and what historical role they have played. Where traditions have caused harm, we document it honestly. Where they have inspired beauty and human flourishing, we document that too.</p>';
echo '<p>This subject is cross-linked with <a href="/subjects/esoterism/intro/">Esoterism</a> — the inner, mystical dimensions of religious traditions.</p>';
echo '</div>';
