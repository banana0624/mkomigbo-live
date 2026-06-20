<?php
declare(strict_types=1);
echo '<div class="mk-prose">';
echo '<p class="mk-muted" style="margin-top:0;">The landscape of esoteric traditions — how they relate, what they share, and what makes each distinctive.</p>';
echo '<h2>The Western Esoteric Traditions</h2>';
echo '<table style="width:100%;border-collapse:collapse;font-size:.88rem;">';
echo '<tr style="border-bottom:2px solid #e5e7eb;"><th style="text-align:left;padding:8px 10px;">Tradition</th><th style="text-align:left;padding:8px 10px;">Origin</th><th style="text-align:left;padding:8px 10px;">Period</th><th style="text-align:left;padding:8px 10px;">Theism</th><th style="text-align:left;padding:8px 10px;">Key Concept</th></tr>';
$western = [
['Hermeticism','Egypt/Alexandria','c. 100–300 CE','Monotheistic-monist','As above, so below; the soul\'s ascent through planetary spheres'],
['Gnosticism','Syria/Egypt/Rome','c. 100–400 CE','Dualistic (True God vs Demiurge)','The material world as prison; gnosis as liberation'],
['Neoplatonism','Alexandria/Rome','3rd–6th century CE','Monist (The One beyond being)','Emanation from the One; the soul\'s return through contemplation'],
['Kabbalah','Spain/Provence','12th–13th century CE','Monotheistic (Ein Sof)','Ten Sefirot; Tikkun Olam; gilgul (soul transmigration)'],
['Alchemy','Egypt/Islamic world/Europe','c. 300 BCE–1700 CE','Varies (often Christian)','Solve et Coagula; the Great Work; inner transformation'],
['Rosicrucianism','Germany','1614 CE','Esoteric Christianity','Universal reformation; invisible brotherhood; rose and cross'],
['Freemasonry','Scotland/England','1717 CE (speculative)','Deistic (Supreme Being)','The Hiramic legend; moral geometry; initiation by degrees'],
['Theosophy','USA (HPB)','1875 CE','Panentheistic-monist','Seven planes; Root Races; Masters; karma and reincarnation'],
['Anthroposophy','Germany (Steiner)','1912 CE','Esoteric Christianity','Spiritual science; Akashic Records; Christ as cosmic event'],
['Thelema','Egypt/England (Crowley)','1904 CE','Polytheistic-pantheist','True Will; Aeons; Holy Guardian Angel; "Do what thou wilt"'],
['Golden Dawn','England','1888 CE','Syncretic magical','Tree of Life; grade system; ceremonial magic synthesis'],
];
foreach($western as $r) {
  echo '<tr style="border-bottom:1px solid #f0f0f0;vertical-align:top;">';
  foreach($r as $i=>$cell) {
    $s = $i===0?'padding:8px 10px;font-weight:700;':'padding:8px 10px;color:#555;font-size:.85rem;';
    echo '<td style="'.$s.'">'.$cell.'</td>';
  }
  echo '</tr>';
}
echo '</table>';

echo '<h2 style="margin-top:28px;">Eastern Mystical Traditions</h2>';
echo '<table style="width:100%;border-collapse:collapse;font-size:.88rem;">';
echo '<tr style="border-bottom:2px solid #e5e7eb;"><th style="text-align:left;padding:8px 10px;">Tradition</th><th style="text-align:left;padding:8px 10px;">Origin</th><th style="text-align:left;padding:8px 10px;">Framework</th><th style="text-align:left;padding:8px 10px;">Key Concept</th></tr>';
$eastern = [
['Sufism','Arabia/Persia','Islamic monotheism','Fana (annihilation in God); Wahdat al-Wujud; dhikr'],
['Tantra','India','Polytheistic/non-dual Shakta-Shaiva','Kundalini; chakras; the body as sacred; transgression as liberation'],
['Kashmir Shaivism','Kashmir India','Non-dual (Shiva consciousness)','Pratyabhijna (recognition); Spanda (divine pulsation); grace'],
['Vajrayana Buddhism','Tibet/India','Non-dual Buddhist','Mandala; mantra; visualization; terma (hidden treasures)'],
['Tibetan Bön','Tibet','Pre-Buddhist indigenous','Dzogchen; nine ways; soul-retrieval; Kuntu Zangpo'],
['Advaita Vedanta','India (Adi Shankara)','Non-dual Hindu','Brahman/Atman identity; maya (illusion); Tat tvam asi'],
['Kundalini Yoga','India','Hindu/Tantric','Serpent energy; chakra system; union of Shakti and Shiva at crown'],
['Zen/Chan','China/Japan','Buddhist non-dual','Direct pointing; koan; sudden enlightenment; just sitting (zazen)'],
];
foreach($eastern as $r) {
  echo '<tr style="border-bottom:1px solid #f0f0f0;vertical-align:top;">';
  foreach($r as $i=>$cell) {
    $s = $i===0?'padding:8px 10px;font-weight:700;':'padding:8px 10px;color:#555;font-size:.85rem;';
    echo '<td style="'.$s.'">'.$cell.'</td>';
  }
  echo '</tr>';
}
echo '</table>';

echo '<h2 style="margin-top:28px;">African Esoteric Traditions</h2>';
echo '<table style="width:100%;border-collapse:collapse;font-size:.88rem;">';
echo '<tr style="border-bottom:2px solid #e5e7eb;"><th style="text-align:left;padding:8px 10px;">Tradition</th><th style="text-align:left;padding:8px 10px;">People/Region</th><th style="text-align:left;padding:8px 10px;">Framework</th><th style="text-align:left;padding:8px 10px;">Key Esoteric Concept</th></tr>';
$african = [
['Ọdinala (inner)','Igbo, southeastern Nigeria','Panentheistic','Chi (personal divine); ọgbanje (cycling souls); ilo uwa (reincarnation); Ma\'at equivalent in Ala'],
['Ifá Inner Teaching','Yoruba, SW Nigeria/diaspora','Panentheistic','Ori (higher self); ayanmo (destiny); iwa pele (character as supreme value); Odu as complete philosophy'],
['Kongo Cosmology','Kongo (DRC/Angola/diaspora)','Ancestral monism','Dikenga dia Kongo (cosmogram); soul cycle; Nkisi as power technology'],
['Kemetic Esoterism','Ancient Egypt','Hidden monotheism (Amun)','Amun as hidden One; Ma\'at as cosmic order; the Akh; mystery traditions'],
['Vodou Esoteric','Haiti/West Africa','Polytheistic/ancestral','Lwa as cosmic forces; Baron Samedi at crossroads; the dead as teachers'],
['Zulu Sangoma','South Africa','Ancestral','Ukuthwasa (shamanic calling through illness); ancestor communication; Ubuntu as metaphysics'],
];
foreach($african as $r) {
  echo '<tr style="border-bottom:1px solid #f0f0f0;vertical-align:top;">';
  foreach($r as $i=>$cell) {
    $s = $i===0?'padding:8px 10px;font-weight:700;':'padding:8px 10px;color:#555;font-size:.85rem;';
    echo '<td style="'.$s.'">'.$cell.'</td>';
  }
  echo '</tr>';
}
echo '</table>';

echo '<h2 style="margin-top:28px;">Cross-Traditional Themes</h2>';
echo '<table style="width:100%;border-collapse:collapse;font-size:.88rem;">';
echo '<tr style="border-bottom:2px solid #e5e7eb;"><th style="text-align:left;padding:8px 10px;">Theme</th><th style="text-align:left;padding:8px 10px;">Western</th><th style="text-align:left;padding:8px 10px;">Eastern</th><th style="text-align:left;padding:8px 10px;">African</th></tr>';
$themes = [
['Reincarnation','Hermeticism, Kabbalah (gilgul), Neoplatonism, Theosophy, Wicca','Hinduism, Buddhism, Jainism, Tantra, Sufism (some)','Ọdinala (ilo uwa), Yoruba (atunwa), Kongo (Dikenga cycle)'],
['The Hidden God','Hermeticism (The All), Gnosticism (True God), Kabbalah (Ein Sof)','Advaita (Brahman), Taoism (Tao), Sufism (Al-Haqq)','Ọdinala (Chukwu beyond approach), Kemet (Amun the Hidden), Yoruba (Olodumare remote)'],
['Initiation','Golden Dawn grades, Freemasonry degrees, Rosicrucian levels','Tantric diksha, Sufi tariqa bay\'ah, Buddhist ordination','Dibia initiation (Agwu calling), Sangoma ukuthwasa, Egungun masquerade'],
['The Divine Within','Hermeticism (man as microcosm), Gnosticism (divine spark)','Advaita (Atman=Brahman), Sufism (fana), Tantra (Shiva in all)','Ọdinala (Chi as divine portion), Yoruba (Ori as divine head), Ubuntu (divine in community)'],
['Moral Causation','Freemasonry (what you build you inhabit), Karma law in Theosophy','Karma (Hindu/Buddhist), Tao (natural consequence in Taoism)','Ọgụ/Ọfọ (clean hands protected), Iwa pele (character determines fate)'],
['Transformation','Alchemy (solve et coagula), Kabbalah (Tikkun), Hermetics (ascent)','Tantra (transmutation of desire), Sufism (fana/baqa), Yoga','Dibia medicine (ogwu), masquerade transformation, ancestor alchemy'],
];
foreach($themes as $r) {
  echo '<tr style="border-bottom:1px solid #f0f0f0;vertical-align:top;">';
  echo '<td style="padding:8px 10px;font-weight:700;color:#553c9a;">'.$r[0].'</td>';
  echo '<td style="padding:8px 10px;color:#555;font-size:.84rem;">'.$r[1].'</td>';
  echo '<td style="padding:8px 10px;color:#555;font-size:.84rem;">'.$r[2].'</td>';
  echo '<td style="padding:8px 10px;color:#555;font-size:.84rem;">'.$r[3].'</td>';
  echo '</tr>';
}
echo '</table>';

echo '<div style="display:flex;gap:10px;flex-wrap:wrap;margin:24px 0 4px;">';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/spirituality/topics/">Western Esoteric</a>';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/spirituality/eastern/">Eastern Mysticism</a>';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/spirituality/african/">African Esoteric</a>';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/spirituality/sources/">Texts & Downloads</a>';
echo '</div></div>';
