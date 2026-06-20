<?php
declare(strict_types=1);
echo '<div class="mk-prose">';
echo '<p class="mk-muted" style="margin-top:0;">The esoteric traditions of humanity — inner teachings, hidden knowledge, mystical philosophy, and the pursuit of direct experience of ultimate reality.</p>';
echo '<h2>What is Esoterism?</h2>';
echo '<p>Esoterism (from Greek <em>esōterikos</em> — "inner") refers to the body of knowledge, philosophy, and practice that claims to penetrate behind the outer forms of religion and convention to reveal hidden or deeper truths about the nature of reality, the human soul, and ultimate being. Where exoteric religion offers public doctrine and communal practice, esoteric tradition offers inner teaching reserved — historically — for those deemed ready to receive it.</p>';
echo '<p>Esoteric traditions are not a single system but a family of related approaches unified by certain recurring themes: the correspondence between macrocosm and microcosm ("As above, so below"), the possibility of direct human experience of the divine, the existence of hidden levels of reality accessible through specific practices, and the transformative potential of knowledge (gnosis).</p>';
echo '<h2>Key Recurring Themes Across Esoteric Traditions</h2>';
echo '<ul>';
echo '<li><strong>Gnosis</strong> — direct, experiential knowledge of the divine; not belief but experience</li>';
echo '<li><strong>Correspondence</strong> — the macrocosm (universe) and microcosm (human being) mirror each other; "As above, so below" (Hermes Trismegistus)</li>';
echo '<li><strong>Transmutation</strong> — the transformation of the practitioner through knowledge and practice; spiritual alchemy</li>';
echo '<li><strong>Hidden levels of reality</strong> — existence has multiple planes or dimensions beyond the physical; the visible world is a reflection of invisible realities</li>';
echo '<li><strong>Initiation</strong> — esoteric knowledge is transmitted through graded initiation; each level reveals more; the uninitiated cannot receive or understand</li>';
echo '<li><strong>The perennial philosophy</strong> — beneath all religions and philosophies lies a single, universal wisdom tradition</li>';
echo '<li><strong>The divine within</strong> — the human soul is divine in essence; liberation is the recognition of this fact</li>';
echo '</ul>';
echo '<h2>Monotheism, Polytheism, and Esoterism</h2>';
echo '<p>Esoteric traditions cut across the monotheism/polytheism divide in interesting ways. Many esoteric systems — Kabbalah, Sufism, Hermeticism — operate within monotheistic frameworks but understand the divine as vastly more complex than popular monotheism suggests. The many divine names, attributes, and intermediaries (angels, sephirot, Orishas) are understood esoterically as aspects, emanations, or faces of the One. Polytheistic esoteric systems (Greek mystery religions, Tantra) use multiple divine figures as maps of consciousness and cosmic forces rather than literal separate beings.</p>';
echo '<h2>Navigate This Subject</h2>';
echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;margin:16px 0;">';
$sections = [
  ['/subjects/spirituality/overview/','🔭 Overview','The landscape of Western and Eastern esoterism — how traditions relate'],
  ['/subjects/spirituality/topics/','📜 Western Esoteric Traditions','Hermeticism, Kabbalah, Gnosticism, Neoplatonism, Alchemy, Rosicrucianism, Freemasonry, Theosophy, Thelema'],
  ['/subjects/spirituality/eastern/','🕉️ Eastern & Sufi Mysticism','Sufism, Tantra, Kundalini, Kashmir Shaivism, Tibetan Bön esoteric practice'],
  ['/subjects/spirituality/african/','🌍 African Esoteric Traditions','Deep Ọdinala philosophy, Yoruba Ifá inner teaching, Kongo cosmogram, Kemetic esoterism'],
  ['/subjects/spirituality/people/','👤 Masters & Initiates','Key figures across all esoteric traditions'],
  ['/subjects/spirituality/sources/','📚 Texts & Downloads','Primary texts with free download links'],
];
foreach($sections as [$href,$title,$desc]) {
  echo '<a href="'.$href.'" style="display:block;padding:14px;border:1px solid #e5e7eb;border-radius:12px;text-decoration:none;color:inherit;background:#fff;transition:box-shadow .12s;" onmouseover="this.style.boxShadow=\'0 4px 16px rgba(0,0,0,.09)\'" onmouseout="this.style.boxShadow=\'\'">';
  echo '<div style="font-weight:800;font-size:.95rem;color:#111;margin-bottom:4px;">'.$title.'</div>';
  echo '<div style="font-size:.82rem;color:#6b7280;line-height:1.4;">'.$desc.'</div>';
  echo '</a>';
}
echo '</div>';
echo '<h2>Esoterism and Igbo Tradition</h2>';
echo '<p>Igbo spiritual tradition contains a rich esoteric dimension — the deep philosophical teachings about Chi (the personal divine force), Chukwu (the Supreme Being beyond names), Ọgbanje (the mystery of souls cycling between worlds), and the dibia\'s initiatory path of knowledge. These are not folk superstitions but a coherent cosmological and metaphysical system comparable in sophistication to any of the world\'s great esoteric traditions. This subject treats Ọdinala\'s esoteric depth with the same seriousness as Hermeticism or Kabbalah.</p>';
echo '<div style="display:flex;gap:10px;flex-wrap:wrap;margin:18px 0 4px;">';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/religion/overview/">→ World Religions (connected subject)</a>';
echo '</div></div>';
