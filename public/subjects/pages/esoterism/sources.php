<?php
declare(strict_types=1);
echo '<div class="mk-prose">';
echo '<p class="mk-muted" style="margin-top:0;">Primary texts of the esoteric traditions — with free download links where available.</p>';
echo '<p>All texts marked <strong>[FREE]</strong> are available as free downloads at the linked source. Sacred-texts.com is the most comprehensive free library of esoteric and religious texts in the world — entirely free, no registration required.</p>';

echo '<h2>Western Esoteric Texts</h2>';
echo '<table style="width:100%;border-collapse:collapse;font-size:.88rem;">';
echo '<tr style="border-bottom:2px solid #e5e7eb;"><th style="text-align:left;padding:8px 10px;">Text</th><th style="text-align:left;padding:8px 10px;">Tradition</th><th style="text-align:left;padding:8px 10px;">Date</th><th style="text-align:left;padding:8px 10px;">Download</th></tr>';
$western_texts = [
  ['Corpus Hermeticum','Hermeticism','c. 100–300 CE','<a href="https://sacred-texts.com/egy/herm/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['The Emerald Tablet (Tabula Smaragdina)','Hermeticism/Alchemy','c. 8th century CE','<a href="https://sacred-texts.com/alc/emerald.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['The Kybalion','Hermeticism','1908 CE','<a href="https://sacred-texts.com/eso/kyb/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['The Gospel of Thomas','Gnosticism','1st–2nd century CE','<a href="https://sacred-texts.com/chr/thomas.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['Pistis Sophia','Gnosticism','3rd–4th century CE','<a href="https://sacred-texts.com/chr/ps/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['The Apocryphon of John','Gnosticism (Sethian)','2nd century CE','<a href="https://gnosis.org/naghamm/apocjn.html" target="_blank" rel="noopener">gnosis.org [FREE]</a>'],
  ['Nag Hammadi Library (complete)','Gnosticism','2nd–4th century CE','<a href="https://gnosis.org/naghamm/nhl.html" target="_blank" rel="noopener">gnosis.org [FREE]</a>'],
  ['The Zohar (selections)','Kabbalah','c. 1280 CE','<a href="https://sacred-texts.com/jud/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['Sefer Yetzirah (Book of Formation)','Kabbalah','c. 2nd–6th century CE','<a href="https://sacred-texts.com/jud/sy/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['The Enneads by Plotinus','Neoplatonism','c. 270 CE','<a href="https://sacred-texts.com/cla/plotenn/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['Paracelsus — Selected Works','Alchemy','16th century CE','<a href="https://sacred-texts.com/alc/paracel/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['The Chymical Wedding of Christian Rosenkreutz','Rosicrucianism','1616 CE','<a href="https://sacred-texts.com/sro/rcia/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['Fama Fraternitatis','Rosicrucianism','1614 CE','<a href="https://sacred-texts.com/sro/rcc/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['Morals and Dogma — Albert Pike','Freemasonry','1871 CE','<a href="https://sacred-texts.com/mas/md/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['The Secret Doctrine — H.P. Blavatsky','Theosophy','1888 CE','<a href="https://sacred-texts.com/the/sd/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['Isis Unveiled — H.P. Blavatsky','Theosophy','1877 CE','<a href="https://sacred-texts.com/the/iu/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['The Key to Theosophy — Blavatsky','Theosophy','1889 CE','<a href="https://sacred-texts.com/the/kt/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['The Voice of the Silence — Blavatsky','Theosophy','1889 CE','<a href="https://sacred-texts.com/the/vs/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['The Book of the Law (Liber AL) — Crowley','Thelema','1904 CE','<a href="https://sacred-texts.com/oto/engccxx.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['The Book of Lies — Crowley','Thelema/Golden Dawn','1913 CE','<a href="https://sacred-texts.com/oto/bl/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['777 and Other Qabalistic Writings — Crowley','Golden Dawn/Thelema','1909 CE','<a href="https://archive.org/search?query=crowley+777" target="_blank" rel="noopener">archive.org [FREE]</a>'],
  ['Three Books of Occult Philosophy — Agrippa','Western Occultism','1531 CE','<a href="https://sacred-texts.com/eso/agrippa/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['The Golden Dawn — Israel Regardie','Golden Dawn','1937 CE','<a href="https://archive.org/search?query=regardie+golden+dawn" target="_blank" rel="noopener">archive.org [FREE]</a>'],
];
foreach($western_texts as $r) {
  echo '<tr style="border-bottom:1px solid #f0f0f0;vertical-align:top;">';
  echo '<td style="padding:8px 10px;font-weight:700;">'.$r[0].'</td>';
  echo '<td style="padding:8px 10px;color:#553c9a;">'.$r[1].'</td>';
  echo '<td style="padding:8px 10px;color:#888;">'.$r[2].'</td>';
  echo '<td style="padding:8px 10px;">'.$r[3].'</td>';
  echo '</tr>';
}
echo '</table>';

echo '<h2 style="margin-top:28px;">Eastern Mystical Texts</h2>';
echo '<table style="width:100%;border-collapse:collapse;font-size:.88rem;">';
echo '<tr style="border-bottom:2px solid #e5e7eb;"><th style="text-align:left;padding:8px 10px;">Text</th><th style="text-align:left;padding:8px 10px;">Tradition</th><th style="text-align:left;padding:8px 10px;">Date</th><th style="text-align:left;padding:8px 10px;">Download</th></tr>';
$eastern_texts = [
  ['Masnavi-ye Ma\'navi — Rumi','Sufism','13th century CE','<a href="https://sacred-texts.com/isl/masnavi/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['Kitab al-Tawasin — Al-Hallaj','Sufism','10th century CE','<a href="https://sacred-texts.com/isl/hallaj/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['Ihya Ulum al-Din — Al-Ghazali','Sufism/Islamic Mysticism','11th century CE','<a href="https://archive.org/search?query=ihya+ulum+al-din+ghazali" target="_blank" rel="noopener">archive.org [FREE]</a>'],
  ['Fusus al-Hikam — Ibn Arabi','Sufism','13th century CE','<a href="https://archive.org/search?query=fusus+al-hikam+ibn+arabi" target="_blank" rel="noopener">archive.org [FREE]</a>'],
  ['The Bhagavad Gita','Hinduism/Vedanta','c. 200 BCE','<a href="https://sacred-texts.com/hin/gita/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['The Upanishads (principal 13)','Hinduism/Vedanta','c. 800–200 BCE','<a href="https://sacred-texts.com/hin/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['Shiva Sutras','Kashmir Shaivism','c. 9th century CE','<a href="https://sacred-texts.com/hin/shivasutra.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['Vijnanabhairava Tantra','Tantra/Kashmir Shaivism','c. 7th century CE','<a href="https://archive.org/search?query=vijnanabhairava+tantra" target="_blank" rel="noopener">archive.org [FREE]</a>'],
  ['Dhammapada','Buddhism','c. 3rd century BCE','<a href="https://sacred-texts.com/bud/dhp.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['The Tibetan Book of the Dead (Bardo Thodol)','Vajrayana Buddhism/Bön','8th century CE','<a href="https://sacred-texts.com/bud/tib/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['Heart Sutra','Mahayana Buddhism','1st–2nd century CE','<a href="https://sacred-texts.com/bud/tib/heartsut.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['Diamond Sutra','Mahayana Buddhism','c. 4th century CE','<a href="https://sacred-texts.com/bud/tib/diam.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['Tao Te Ching — Laozi','Taoism','c. 400 BCE','<a href="https://sacred-texts.com/tao/taote.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['Zhuangzi','Taoism','c. 4th century BCE','<a href="https://sacred-texts.com/tao/creed.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['The Analects — Confucius','Confucianism','c. 5th century BCE','<a href="https://sacred-texts.com/cfu/conf1.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['Guru Granth Sahib (English translation)','Sikhism','1604 CE (final 1708)','<a href="https://sacred-texts.com/skh/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['The Avesta (Gathas of Zarathustra)','Zoroastrianism','c. 1500–600 BCE','<a href="https://sacred-texts.com/zor/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
];
foreach($eastern_texts as $r) {
  echo '<tr style="border-bottom:1px solid #f0f0f0;vertical-align:top;">';
  echo '<td style="padding:8px 10px;font-weight:700;">'.$r[0].'</td>';
  echo '<td style="padding:8px 10px;color:#276749;">'.$r[1].'</td>';
  echo '<td style="padding:8px 10px;color:#888;">'.$r[2].'</td>';
  echo '<td style="padding:8px 10px;">'.$r[3].'</td>';
  echo '</tr>';
}
echo '</table>';

echo '<h2 style="margin-top:28px;">African Spiritual Texts</h2>';
echo '<table style="width:100%;border-collapse:collapse;font-size:.88rem;">';
echo '<tr style="border-bottom:2px solid #e5e7eb;"><th style="text-align:left;padding:8px 10px;">Text / Source</th><th style="text-align:left;padding:8px 10px;">Tradition</th><th style="text-align:left;padding:8px 10px;">Access</th></tr>';
$african_texts = [
  ['The Book of the Dead (Papyrus of Ani)','Kemet','<a href="https://sacred-texts.com/egy/ebod/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['The Pyramid Texts','Kemet','<a href="https://sacred-texts.com/egy/pyt/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['On Isis and Osiris — Plutarch','Kemet (Greek account)','<a href="https://sacred-texts.com/cla/plu/mor/mor360.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['Popol Vuh (Maya creation narrative)','Maya Religion','<a href="https://sacred-texts.com/nam/maya/pvgm/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['Ifá: An Exposition — Wande Abimbola','Yoruba Ifá','Academic library / purchase — no free download available'],
  ['Ifá Will Mend Our Broken World — Abimbola','Yoruba Ifá','Purchase — Aim Books'],
  ['Kongo: Power and Majesty — catalogue','Kongo religion','Metropolitan Museum of Art publication'],
  ['Things Fall Apart — Chinua Achebe','Ọdinala (Igbo)','Purchase — primary literary source on Igbo traditional life'],
  ['Arrow of God — Chinua Achebe','Ọdinala (Igbo)','Purchase — deepest literary treatment of Igbo religion'],
  ['Victor Uchendu — The Igbo of Southeast Nigeria','Ọdinala (Igbo)','Academic library'],
];
foreach($african_texts as $r) {
  echo '<tr style="border-bottom:1px solid #f0f0f0;vertical-align:top;">';
  echo '<td style="padding:8px 10px;font-weight:700;">'.$r[0].'</td>';
  echo '<td style="padding:8px 10px;color:#2d6a1f;">'.$r[1].'</td>';
  echo '<td style="padding:8px 10px;">'.$r[2].'</td>';
  echo '</tr>';
}
echo '</table>';

echo '<h2 style="margin-top:28px;">Ancient Religious Texts</h2>';
echo '<table style="width:100%;border-collapse:collapse;font-size:.88rem;">';
echo '<tr style="border-bottom:2px solid #e5e7eb;"><th style="text-align:left;padding:8px 10px;">Text</th><th style="text-align:left;padding:8px 10px;">Tradition</th><th style="text-align:left;padding:8px 10px;">Download</th></tr>';
$ancient_texts = [
  ['The Epic of Gilgamesh','Mesopotamian','<a href="https://sacred-texts.com/ane/eog/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['Enuma Elish (Babylonian Creation Epic)','Mesopotamian','<a href="https://sacred-texts.com/ane/enuma.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['The Descent of Inanna','Sumerian','<a href="https://sacred-texts.com/ane/inanna.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['Hesiod — Theogony (Greek Gods)','Greek Religion','<a href="https://sacred-texts.com/cla/hesiod/theogony.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['The Homeric Hymns','Greek Religion','<a href="https://sacred-texts.com/cla/homer/hymns.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['The Prose Edda — Snorri Sturluson','Norse Religion','<a href="https://sacred-texts.com/neu/pre/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['The Poetic Edda','Norse Religion','<a href="https://sacred-texts.com/neu/poe/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['Popol Vuh','Maya Religion','<a href="https://sacred-texts.com/nam/maya/pvgm/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
];
foreach($ancient_texts as $r) {
  echo '<tr style="border-bottom:1px solid #f0f0f0;vertical-align:top;">';
  echo '<td style="padding:8px 10px;font-weight:700;">'.$r[0].'</td>';
  echo '<td style="padding:8px 10px;color:#b7791f;">'.$r[1].'</td>';
  echo '<td style="padding:8px 10px;">'.$r[2].'</td>';
  echo '</tr>';
}
echo '</table>';

echo '<h2 style="margin-top:28px;">Abrahamic Sacred Texts</h2>';
echo '<table style="width:100%;border-collapse:collapse;font-size:.88rem;">';
echo '<tr style="border-bottom:2px solid #e5e7eb;"><th style="text-align:left;padding:8px 10px;">Text</th><th style="text-align:left;padding:8px 10px;">Tradition</th><th style="text-align:left;padding:8px 10px;">Download</th></tr>';
$abrahamic_texts = [
  ['The Torah / Hebrew Bible (Tanakh)','Judaism','<a href="https://sacred-texts.com/jud/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['The Talmud (Babylonian, selections)','Judaism','<a href="https://sacred-texts.com/jud/t01/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['The Zohar (selections)','Kabbalah/Judaism','<a href="https://sacred-texts.com/jud/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['The Bible (King James Version)','Christianity','<a href="https://sacred-texts.com/bib/kjv/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['New Testament (multiple translations)','Christianity','<a href="https://biblegateway.com" target="_blank" rel="noopener">biblegateway.com [FREE]</a>'],
  ['The Quran (Yusuf Ali translation)','Islam','<a href="https://sacred-texts.com/isl/quran/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['Quran (multiple translations + audio)','Islam','<a href="https://quran.com" target="_blank" rel="noopener">quran.com [FREE]</a>'],
  ['Sahih Bukhari (Hadith)','Islam','<a href="https://sacred-texts.com/isl/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['The Book of Mormon','Mormonism','<a href="https://churchofjesuschrist.org/study/scriptures/bofm" target="_blank" rel="noopener">churchofjesuschrist.org [FREE]</a>'],
  ['The Kitab-i-Aqdas — Baha\'u\'llah','Baháʼí Faith','<a href="https://bahai.org/library/authoritative-texts" target="_blank" rel="noopener">bahai.org [FREE]</a>'],
  ['The Hidden Words — Baha\'u\'llah','Baháʼí Faith','<a href="https://sacred-texts.com/bhi/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
  ['The Spirits\' Book — Allan Kardec','Spiritism','<a href="https://sacred-texts.com/spi/spirits/index.htm" target="_blank" rel="noopener">sacred-texts.com [FREE]</a>'],
];
foreach($abrahamic_texts as $r) {
  echo '<tr style="border-bottom:1px solid #f0f0f0;vertical-align:top;">';
  echo '<td style="padding:8px 10px;font-weight:700;">'.$r[0].'</td>';
  echo '<td style="padding:8px 10px;color:#2b6cb0;">'.$r[1].'</td>';
  echo '<td style="padding:8px 10px;">'.$r[2].'</td>';
  echo '</tr>';
}
echo '</table>';

echo '<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:12px;padding:16px;margin:24px 0;">';
echo '<h3 style="margin-top:0;">Primary Free Libraries</h3>';
echo '<ul style="margin:0;">';
echo '<li><a href="https://sacred-texts.com" target="_blank" rel="noopener"><strong>sacred-texts.com</strong></a> — the most comprehensive free library of religious and esoteric texts on the internet. No registration. No cost. Thousands of texts.</li>';
echo '<li><a href="https://archive.org" target="_blank" rel="noopener"><strong>archive.org (Internet Archive)</strong></a> — millions of free books including rare esoteric and religious texts.</li>';
echo '<li><a href="https://gnosis.org" target="_blank" rel="noopener"><strong>gnosis.org</strong></a> — the Gnostic Society Library; Gnostic texts, Neoplatonic texts, Hermetic texts.</li>';
echo '<li><a href="https://sefaria.org" target="_blank" rel="noopener"><strong>sefaria.org</strong></a> — complete Jewish library: Torah, Talmud, Midrash, Kabbalah, in Hebrew and English.</li>';
echo '<li><a href="https://quran.com" target="_blank" rel="noopener"><strong>quran.com</strong></a> — Quran with multiple translations and audio recitation.</li>';
echo '<li><a href="https://accesstoinsight.org" target="_blank" rel="noopener"><strong>accesstoinsight.org</strong></a> — Theravada Buddhist texts (Pali Canon) in English.</li>';
echo '<li><a href="https://buddhanet.net" target="_blank" rel="noopener"><strong>buddhanet.net</strong></a> — Buddhist texts, audio, and education across all traditions.</li>';
echo '</ul>';
echo '</div>';

echo '<div style="display:flex;gap:10px;flex-wrap:wrap;margin:24px 0 4px;">';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/spirituality/intro/">Introduction</a>';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/spirituality/topics/">Western Esoteric</a>';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/spirituality/eastern/">Eastern Mysticism</a>';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/spirituality/african/">African Esoteric</a>';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/religion/sources/">→ Religion Sources</a>';
echo '</div></div>';
