<?php
declare(strict_types=1);
echo '<div class="mk-prose">';
echo '<p class="mk-muted" style="margin-top:0;">The complete map of world religious traditions — ancient, living, Eastern, Western, African, and modern.</p>';

echo '<h2>Ancient and Dead Religions</h2>';
echo '<table style="width:100%;border-collapse:collapse;font-size:.9rem;">';
echo '<tr style="border-bottom:2px solid #e5e7eb;"><th style="text-align:left;padding:8px 12px;">Religion</th><th style="text-align:left;padding:8px 12px;">Region</th><th style="text-align:left;padding:8px 12px;">Period</th><th style="text-align:left;padding:8px 12px;">Key Deity/Concept</th></tr>';
$ancient = [
['Kemet (Egyptian)','Egypt / North Africa','c. 3100 BCE – 400 CE','Ra, Osiris, Isis, Horus, Maat'],
['Sumerian','Mesopotamia (Iraq)','c. 3500 – 1800 BCE','Enlil, Enki, Inanna, An'],
['Babylonian / Akkadian','Mesopotamia','c. 2350 – 539 BCE','Marduk, Ishtar, Anu'],
['Canaanite','Levant (Israel/Palestine)','c. 3000 – 200 BCE','El, Baal, Asherah, Mot'],
['Greek','Ancient Greece','c. 800 – 400 CE','Zeus, Athena, Apollo, Dionysus'],
['Roman','Roman Empire','c. 700 BCE – 400 CE','Jupiter, Juno, Mars, Venus'],
['Norse / Germanic','Scandinavia / Northern Europe','c. 200 – 1100 CE','Odin, Thor, Freya, Loki'],
['Celtic / Druidic','Western Europe / Britain','c. 600 BCE – 400 CE','Cernunnos, Brigid, Lugh, the Dagda'],
['Maya','Mesoamerica (Mexico/Guatemala)','c. 2000 BCE – 1500 CE','Itzamna, Kukulkan, Ix Chel, Ah Puch'],
['Aztec','Central Mexico','c. 1300 – 1521 CE','Huitzilopochtli, Quetzalcoatl, Tlaloc, Tezcatlipoca'],
['Inca','Andean South America','c. 1400 – 1533 CE','Inti (Sun God), Pachamama, Viracocha'],
['Mesopotamian (general)','Iraq / Syria / Iran','c. 3500 – 500 BCE','Varied pantheon by city-state'],
['Persian (pre-Zoroastrian)','Iran / Central Asia','c. 1500 – 600 BCE','Ahura Mazda (early), Mithra'],
];
foreach($ancient as $r) {
  echo '<tr style="border-bottom:1px solid #f0f0f0;">';
  foreach($r as $i=>$cell) {
    $style = $i===0 ? 'padding:8px 12px;font-weight:700;' : 'padding:8px 12px;color:#555;';
    echo '<td style="'.$style.'">'.$cell.'</td>';
  }
  echo '</tr>';
}
echo '</table>';

echo '<h2 style="margin-top:28px;">Eastern Traditions</h2>';
echo '<table style="width:100%;border-collapse:collapse;font-size:.9rem;">';
echo '<tr style="border-bottom:2px solid #e5e7eb;"><th style="text-align:left;padding:8px 12px;">Religion</th><th style="text-align:left;padding:8px 12px;">Origin</th><th style="text-align:left;padding:8px 12px;">Founded</th><th style="text-align:left;padding:8px 12px;">Followers (approx)</th></tr>';
$eastern = [
['Hinduism','Indian subcontinent','c. 1500 BCE (Vedic period)','1.2 billion'],
['Buddhism','India (Nepal border)','c. 500 BCE (Siddhartha Gautama)','500 million'],
['Jainism','India','c. 600 BCE (Mahavira)','6 million'],
['Sikhism','Punjab, India','1499 CE (Guru Nanak)','25 million'],
['Confucianism','China','c. 500 BCE (Confucius)','6 million (formal)'],
['Taoism','China','c. 400 BCE (Laozi attributed)','20 million'],
['Shinto','Japan','c. 700 BCE (indigenous)','3-4 million (formal)'],
['Zoroastrianism','Persia (Iran)','c. 1500 BCE (Zarathustra)','200,000'],
['Bon','Tibet','Pre-Buddhist, c. 1000 BCE','Unknown'],
['Tenrikyo','Japan','1838 CE (Miki Nakayama)','2 million'],
];
foreach($eastern as $r) {
  echo '<tr style="border-bottom:1px solid #f0f0f0;">';
  foreach($r as $i=>$cell) {
    $style = $i===0 ? 'padding:8px 12px;font-weight:700;' : 'padding:8px 12px;color:#555;';
    echo '<td style="'.$style.'">'.$cell.'</td>';
  }
  echo '</tr>';
}
echo '</table>';

echo '<h2 style="margin-top:28px;">Abrahamic Religions</h2>';
echo '<table style="width:100%;border-collapse:collapse;font-size:.9rem;">';
echo '<tr style="border-bottom:2px solid #e5e7eb;"><th style="text-align:left;padding:8px 12px;">Religion/Branch</th><th style="text-align:left;padding:8px 12px;">Origin</th><th style="text-align:left;padding:8px 12px;">Founded</th><th style="text-align:left;padding:8px 12px;">Followers</th></tr>';
$abrahamic = [
['Judaism','Canaan / Levant','c. 1800 BCE (Abraham) / 1300 BCE (Moses)','15 million'],
['Christianity','Roman Palestine','c. 30 CE (Jesus of Nazareth)','2.4 billion'],
['— Roman Catholicism','Rome','Split formalized 1054 CE','1.3 billion'],
['— Eastern Orthodoxy','Constantinople/Eastern Europe','1054 CE split','220 million'],
['— Protestantism','Germany/Switzerland','1517 CE (Luther, Calvin)','900 million'],
['— Anglicanism','England','1534 CE (Henry VIII)','85 million'],
['— Pentecostalism','USA','1906 CE (Azusa Street)','700 million'],
['Islam','Arabia','610 CE (Muhammad ibn Abdullah)','1.9 billion'],
['— Sunni','Arabia/Global','632 CE (after Muhammad\'s death)','1.5 billion'],
['— Shia','Arabia/Persia','632 CE (Ali ibn Abi Talib)','300 million'],
['— Sufi','Global','8th–9th century CE','Unknown millions'],
['— Ahmadiyya','Punjab, India','1889 CE (Mirza Ghulam Ahmad)','10-20 million'],
['Druze','Lebanon/Syria/Israel','11th century CE','1 million'],
['Mandaeism','Iraq/Iran','1st–3rd century CE','60,000-70,000'],
];
foreach($abrahamic as $r) {
  echo '<tr style="border-bottom:1px solid #f0f0f0;">';
  foreach($r as $i=>$cell) {
    $style = $i===0 ? 'padding:8px 12px;font-weight:700;' : 'padding:8px 12px;color:#555;';
    echo '<td style="'.$style.'">'.$cell.'</td>';
  }
  echo '</tr>';
}
echo '</table>';

echo '<h2 style="margin-top:28px;">African Traditional Religions</h2>';
echo '<table style="width:100%;border-collapse:collapse;font-size:.9rem;">';
echo '<tr style="border-bottom:2px solid #e5e7eb;"><th style="text-align:left;padding:8px 12px;">Tradition</th><th style="text-align:left;padding:8px 12px;">People/Region</th><th style="text-align:left;padding:8px 12px;">Key Concepts</th></tr>';
$african = [
['Ọdinala (Igbo)','Igbo, southeastern Nigeria','Chukwu, Chi, Ala, Alusi, Ọgbanje, Ọfọ'],
['Ifá / Yoruba','Yoruba, southwestern Nigeria','Olodumare, Orishas, Ifá divination, Egungun'],
['Akan Religion','Akan, Ghana/Côte d\'Ivoire','Nyame, Asase Ya, Obosom, Stools'],
['Zulu / Nguni','Zulu, South Africa','Unkulunkulu, Amadlozi (ancestors), Isangoma'],
['Kongo Religion','Kongo, DRC/Angola/Congo','Nzambi, Minkisi, Kindoki'],
['Vodou (Haitian)','Haiti / West Africa origins','Bondye, Lwa, Baron Samedi — African diaspora'],
['Candomblé','Brazil (Yoruba origins)','Orixás — African diaspora tradition'],
['Umbanda','Brazil','Syncretism of Yoruba, Spiritism, Catholicism, Indigenous'],
['San / Bushman','Southern Africa','!Gao Na, Trance dance, Spirit world'],
['Dogon','Mali','Amma, Nommo, astronomical cosmology'],
['Bori','Hausa, northern Nigeria/Niger','Spirit possession tradition'],
];
foreach($african as $r) {
  echo '<tr style="border-bottom:1px solid #f0f0f0;">';
  foreach($r as $i=>$cell) {
    $style = $i===0 ? 'padding:8px 12px;font-weight:700;' : 'padding:8px 12px;color:#555;';
    echo '<td style="'.$style.'">'.$cell.'</td>';
  }
  echo '</tr>';
}
echo '</table>';

echo '<h2 style="margin-top:28px;">Modern and New Religious Movements</h2>';
echo '<table style="width:100%;border-collapse:collapse;font-size:.9rem;">';
echo '<tr style="border-bottom:2px solid #e5e7eb;"><th style="text-align:left;padding:8px 12px;">Religion</th><th style="text-align:left;padding:8px 12px;">Founded</th><th style="text-align:left;padding:8px 12px;">Founder</th><th style="text-align:left;padding:8px 12px;">Followers</th></tr>';
$modern = [
['Baháʼí Faith','1844 CE, Persia','The Báb / Baháʼu\'lláh','8 million'],
['Mormonism (LDS)','1830 CE, USA','Joseph Smith','17 million'],
['Seventh-day Adventism','1863 CE, USA','Ellen G. White / James White','22 million'],
['Jehovah\'s Witnesses','1870s CE, USA','Charles Taze Russell','8.7 million'],
['Christian Science','1866 CE, USA','Mary Baker Eddy','Unknown'],
['Spiritism / Kardecism','1857 CE, France','Allan Kardec','15 million (Brazil-centered)'],
['Rastafari','1930s CE, Jamaica','Various — Haile Selassie as Messiah','1 million'],
['Cao Dai','1926 CE, Vietnam','Ngo Van Chieu','4-6 million'],
['Tenrikyo','1838 CE, Japan','Miki Nakayama','2 million'],
['Eckankar','1965 CE, USA','Paul Twitchell','50,000+'],
['Scientology','1954 CE, USA','L. Ron Hubbard','Unknown (claimed millions)'],
['Unitarian Universalism','1961 CE (formal), USA/UK','Merged traditions','500,000'],
['New Age Movement','1970s–1980s, Global','No single founder','Diffuse, millions'],
['Wicca / Neo-Paganism','1954 CE, UK','Gerald Gardner','1-3 million'],
['Satanism (LaVeyan)','1966 CE, USA','Anton LaVey','Unknown'],
['Nation of Islam','1930 CE, USA','Wallace Fard Muhammad','50,000'],
['Cao Dai','1926 CE, Vietnam','Ngo Van Chieu','4-6 million'],
['Falun Gong (Falun Dafa)','1992 CE, China','Li Hongzhi','Unknown millions'],
['Church of the SubGenius','1980 CE, USA','Ivan Stang (satirical)','N/A'],
];
foreach($modern as $r) {
  echo '<tr style="border-bottom:1px solid #f0f0f0;">';
  foreach($r as $i=>$cell) {
    $style = $i===0 ? 'padding:8px 12px;font-weight:700;' : 'padding:8px 12px;color:#555;';
    echo '<td style="'.$style.'">'.$cell.'</td>';
  }
  echo '</tr>';
}
echo '</table>';
echo '<div style="display:flex;gap:10px;flex-wrap:wrap;margin:28px 0 4px;">';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/religion/intro/">Introduction</a>';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/religion/topics/">Ancient Religions</a>';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/religion/people/">Founders</a>';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/religion/sources/">Sources</a>';
echo '</div></div>';
