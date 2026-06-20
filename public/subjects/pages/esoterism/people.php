<?php
declare(strict_types=1);
echo '<div class="mk-prose">';
echo '<p class="mk-muted" style="margin-top:0;">Masters, initiates, teachers, and key figures across all esoteric traditions.</p>';
$people = [
  ['c. 1–300 CE','Hermes Trismegistus','Hermeticism','Legendary — synthesis of Hermes and Thoth. The Corpus Hermeticum is attributed to him. May represent a tradition rather than a single person. The foundational figure of Western esoterism.'],
  ['c. 205–270 CE','Plotinus','Neoplatonism','The greatest philosopher of late antiquity. His Enneads describe the structure of divine reality (The One, Nous, Psyche) and the soul\'s path of return. Mystical experience recorded in the first person. Foundational for all subsequent Western mysticism.'],
  ['c. 233–305 CE','Porphyry','Neoplatonism','Student and biographer of Plotinus. Edited and published the Enneads. His Life of Plotinus is the primary source on Plotinus. Also wrote Against the Christians — the most sophisticated pagan critique of Christianity.'],
  ['c. 245–325 CE','Iamblichus','Neoplatonism/Theurgy','Syrian Neoplatonist who developed theurgy (ritual operations to ascend through divine levels). His De Mysteriis is the foundational text of Neoplatonic ritual practice. He transformed Neoplatonism from a contemplative to a ritual tradition.'],
  ['c. 412–485 CE','Proclus','Neoplatonism','The last great systematic Neoplatonist. His Elements of Theology is the most rigorous logical account of Neoplatonic metaphysics. Profoundly influenced Islamic philosophy and Christian mysticism (via Pseudo-Dionysius).'],
  ['c. 500 CE','Pseudo-Dionysius the Areopagite','Christian Mysticism/Neoplatonism','Anonymous author of foundational Christian mystical texts (Divine Names, Mystical Theology). Applied Neoplatonic philosophy to Christian theology. The Mystical Theology is the foundational text of Christian apophatic (negative) theology.'],
  ['c. 717–801 CE','Rabia al-Adawiyya','Sufism','The first great female Sufi saint. Introduced the concept of divine love (mahabbah) as the path to God — loving God for God\'s own sake, not for reward or out of fear. Her poetry established the language of Sufi devotion.'],
  ['857–922 CE','Al-Hallaj','Sufism','Persian Sufi mystic executed for declaring "Ana\'l-Haqq" (I am the Truth/God). His death became the supreme symbol of the price of mystical insight. His Kitab al-Tawasin remains one of the most important Sufi texts.'],
  ['1058–1111 CE','Al-Ghazali','Islamic Mysticism','The most important figure in the reconciliation of Sufism with orthodox Islam. His Ihya Ulum al-Din (Revival of the Religious Sciences) integrates Sufi practice into Islamic observance. He gave Sufism theological respectability.'],
  ['1165–1240 CE','Ibn Arabi','Sufism','The "Greatest Master" (al-Shaykh al-Akbar). His doctrine of Wahdat al-Wujud (Unity of Being) is the most sophisticated metaphysical formulation in Islamic thought. His Fusus al-Hikam and Futuhat al-Makkiyya are his major works.'],
  ['1207–1273 CE','Jalal ad-Din Rumi','Sufism','The greatest mystical poet of the Islamic world. The Masnavi-ye Ma\'navi (25,000 verses) is the supreme work of Persian Sufi literature. His poetry on divine love, longing, and union is the most widely read Sufi text globally.'],
  ['c. 1240–1305 CE','Moses de León','Kabbalah','The primary author/compiler of the Zohar — the central text of Kabbalah, attributed by him to the 2nd-century sage Shimon bar Yochai. Whether this attribution is genuine or a literary device remains debated.'],
  ['1445–1510 CE','Marsilio Ficino','Renaissance Hermeticism','Florentine philosopher who translated the Corpus Hermeticum into Latin (1463) for Cosimo de\' Medici. His translations made Hermetic philosophy available to Renaissance Europe and triggered the Renaissance magical tradition.'],
  ['1463–1494 CE','Giovanni Pico della Mirandola','Renaissance Kabbalah','Italian humanist who first systematically combined Kabbalah with Christian theology (Christian Kabbalah). His Oration on the Dignity of Man is the founding document of Renaissance humanism.'],
  ['1493–1541 CE','Paracelsus','Alchemy/Medicine','Swiss-German physician and alchemist who revolutionized medicine and alchemy. Introduced sulfur, mercury, and salt as the three alchemical principles. His work connects alchemy to medicine and esoteric philosophy.'],
  ['1534–1600 CE','Giordano Bruno','Hermeticism/Cosmology','Italian philosopher who combined Hermeticism with Copernican astronomy to argue for an infinite universe with innumerable worlds. Burned at the stake by the Roman Inquisition in 1600. A martyr of both science and esoterism.'],
  ['1575–1624 CE','Jakob Böhme','Christian Mysticism/Theosophy','German shoemaker who had a mystical illumination and wrote a series of profound mystical texts about God, creation, and the nature of good and evil. His Theosophia Revelata influenced Schelling, Hegel, and later theosophy.'],
  ['1724–1804 CE','Immanuel Swedenborg','Christian Mysticism','Swedish scientist who claimed to visit heaven and hell in visionary states and wrote detailed accounts. His Heaven and Hell (1758) influenced Blake, Goethe, and the Spiritualist movement.'],
  ['1831–1891 CE','Helena Petrovna Blavatsky','Theosophy','Co-founder of the Theosophical Society. Her Secret Doctrine and Isis Unveiled synthesized Hindu, Buddhist, and Western esoteric thought into a comprehensive modern framework. The most influential single figure in modern Western esoterism.'],
  ['1861–1925 CE','Rudolf Steiner','Anthroposophy','Austrian philosopher who left the Theosophical Society to found Anthroposophy — an esoteric Christianity with applications in education (Waldorf), agriculture (biodynamics), medicine, and architecture. The most practically applied esoteric tradition.'],
  ['1865–1936 CE','Israel Regardie','Hermetic Order of the Golden Dawn','Secretary to Aleister Crowley; later published the complete Golden Dawn system (1937-1940), making the most sophisticated Western magical system publicly available. Without Regardie, much Golden Dawn knowledge would have been lost.'],
  ['1875–1947 CE','Aleister Crowley','Thelema/Golden Dawn','The most controversial figure in modern Western occultism. His Book of the Law, magical system, and prolific writings made him both the most influential and most reviled figure in 20th-century esoterism. Called himself "the Great Beast 666."'],
  ['1868–1961 CE','Alice Bailey','Theosophy/New Age','Theosophist who claimed to receive teachings from a Tibetan Master (Djwhal Khul). Her 24 books, written over 30 years, constitute the most systematic modern esoteric cosmology and gave the New Age movement much of its vocabulary.'],
  ['950–1020 CE','Abhinavagupta','Kashmir Shaivism','The greatest philosopher of Kashmir Shaivism. His Tantraloka (A Light on Tantra) is the most comprehensive treatment of Tantric philosophy and practice. His Pratyabhijnahridayam (Heart of Recognition) is the most accessible summary of the tradition.'],
  ['c. 800 BCE','Homer (attributed)','Greek Mystery Religion','The Iliad and Odyssey encode, according to esoteric interpreters from antiquity onward, spiritual teachings about the soul\'s descent into matter and return to the divine. Neoplatonists wrote extensively on the "inner Homer."'],
  ['1804–1869 CE','Allan Kardec','Spiritism','French educator who compiled spirit communications into a systematic philosophy of reincarnation and spiritual evolution. His five books constitute the canon of Spiritism, which has ~15 million followers, primarily in Brazil.'],
];

echo '<table style="width:100%;border-collapse:collapse;font-size:.87rem;">';
echo '<tr style="border-bottom:2px solid #e5e7eb;"><th style="text-align:left;padding:8px 10px;">Period</th><th style="text-align:left;padding:8px 10px;">Name</th><th style="text-align:left;padding:8px 10px;">Tradition</th><th style="text-align:left;padding:8px 10px;">Significance</th></tr>';
foreach($people as $r) {
  echo '<tr style="border-bottom:1px solid #f0f0f0;vertical-align:top;">';
  echo '<td style="padding:9px 10px;color:#888;white-space:nowrap;font-size:.8rem;">'.$r[0].'</td>';
  echo '<td style="padding:9px 10px;font-weight:800;color:#111;white-space:nowrap;">'.$r[1].'</td>';
  echo '<td style="padding:9px 10px;color:#553c9a;font-size:.82rem;">'.$r[2].'</td>';
  echo '<td style="padding:9px 10px;color:#374151;line-height:1.5;">'.$r[3].'</td>';
  echo '</tr>';
}
echo '</table>';
echo '<div style="display:flex;gap:10px;flex-wrap:wrap;margin:24px 0 4px;">';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/spirituality/topics/">Western Esoteric</a>';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/spirituality/eastern/">Eastern Mysticism</a>';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/spirituality/african/">African Esoteric</a>';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/spirituality/sources/">Texts & Downloads</a>';
echo '</div></div>';
