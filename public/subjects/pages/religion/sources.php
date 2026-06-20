<?php
declare(strict_types=1);
echo '<div class="mk-prose">';
echo '<p class="mk-muted" style="margin-top:0;">Sacred texts, scholarly references, and further reading across all religious traditions.</p>';
echo '<h2>Sacred Texts by Tradition</h2>';
echo '<table style="width:100%;border-collapse:collapse;font-size:.88rem;">';
echo '<tr style="border-bottom:2px solid #e5e7eb;"><th style="text-align:left;padding:8px 10px;">Tradition</th><th style="text-align:left;padding:8px 10px;">Primary Scripture(s)</th><th style="text-align:left;padding:8px 10px;">Notes</th></tr>';
$texts = [
['Kemet (Egyptian)','Book of the Dead, Pyramid Texts, Coffin Texts, Amduat','Pyramid Texts (c. 2400 BCE) are the oldest religious texts in the world'],
['Mesopotamian','Epic of Gilgamesh, Enuma Elish, Descent of Inanna','Gilgamesh contains the oldest flood narrative, predating the biblical account'],
['Hinduism','Vedas, Upanishads, Bhagavad Gita, Mahabharata, Ramayana, Puranas','Over 100 texts; Bhagavad Gita is most widely read worldwide'],
['Buddhism','Pali Canon (Theravada), Mahayana Sutras, Tibetan Book of the Dead','Dhammapada is most accessible entry point'],
['Jainism','Agamas (canonical texts), Tattvartha Sutra','Largely preserved in Prakrit language'],
['Sikhism','Guru Granth Sahib','Treated as living Guru; 1,430 pages; in Gurmukhi script'],
['Confucianism','The Analects, Four Books, Five Classics','Analects are the most direct record of Confucius\'s teaching'],
['Taoism','Tao Te Ching, Zhuangzi, Liezi, Daozang','Tao Te Ching is one of the most translated books in history'],
['Shinto','Kojiki (712 CE), Nihon Shoki (720 CE)','No single canon; tradition is primarily ritual, not scriptural'],
['Zoroastrianism','The Avesta (Gathas, Yasna, Yashts, Vendidad)','Only ~25% of original Avesta survives'],
['Judaism','Torah, Tanakh, Talmud, Midrash, Zohar (Kabbalah)','Talmud (Babylonian) is the central text of Rabbinic Judaism'],
['Christianity','The Bible (Old and New Testaments)','66 books (Protestant), 73 (Catholic), more (Orthodox)'],
['Islam','The Quran, Hadith collections (Bukhari, Muslim, etc.)','Quran in Arabic considered the literal word of God; untranslatable in the strict sense'],
['Yoruba/Ifá','Ifá corpus (256 Odù, 800+ verses each)','Oral tradition; inscribed on UNESCO Intangible Heritage list'],
['Ọdinala (Igbo)','No written scripture; transmitted through ritual, oral literature, masquerade','Living oral tradition'],
['Baháʼí','Kitáb-i-Aqdas, Hidden Words, Seven Valleys, Writings of ʻAbdu\'l-Bahá','All written by Baháʼu\'lláh or authorized interpreters'],
['Mormonism (LDS)','Bible (KJV), Book of Mormon, Doctrine and Covenants, Pearl of Great Price','Four standard works accepted as scripture'],
['Rastafari','The Bible (especially Psalms, Revelation, Old Testament)','Read through Afrocentric lens; no separate scripture'],
['Cao Dai','The Divine Path to God (Thánh Ngôn Hiệp Tuyển)','Compiled from spirit communications received through mediums'],
['Eckankar','Shariyat-Ki-Sugmad, works of Paul Twitchell and Harold Klemp','Claim to ancient knowledge revealed through ECK Masters'],
['Spiritism','The Spirits\' Book, Mediums\' Book, Gospel According to Spiritism','Five core works of Allan Kardec'],
['Wicca','Book of Shadows (personal), Drawing Down the Moon','No central canon; Gardner\'s and Valiente\'s work most foundational'],
];
foreach($texts as [$trad,$texts_str,$notes]) {
  echo '<tr style="border-bottom:1px solid #f0f0f0;vertical-align:top;">';
  echo '<td style="padding:9px 10px;font-weight:700;color:#111;">'.$trad.'</td>';
  echo '<td style="padding:9px 10px;color:#374151;">'.$texts_str.'</td>';
  echo '<td style="padding:9px 10px;color:#6b7280;font-size:.82rem;">'.$notes.'</td>';
  echo '</tr>';
}
echo '</table>';
echo '<h2 style="margin-top:28px;">Key Academic Works</h2>';
echo '<ul>';
echo '<li><strong>Mircea Eliade</strong> — <em>The Sacred and the Profane</em> (1957); <em>A History of Religious Ideas</em> (3 vols). The foundational academic framework for comparative religion.</li>';
echo '<li><strong>Karen Armstrong</strong> — <em>A History of God</em> (1993); <em>The Battle for God</em> (2000). Accessible scholarly history of the Abrahamic traditions.</li>';
echo '<li><strong>Huston Smith</strong> — <em>The World\'s Religions</em> (1958, rev. 1991). The most widely used introductory text in comparative religion courses.</li>';
echo '<li><strong>Ninian Smart</strong> — <em>The World\'s Religions</em> (1989). Systematic framework using the "seven dimensions of religion."</li>';
echo '<li><strong>Joseph Campbell</strong> — <em>The Hero with a Thousand Faces</em> (1949). Comparative mythology; the monomyth across religious traditions.</li>';
echo '<li><strong>Rudolf Otto</strong> — <em>The Idea of the Holy</em> (1917). Phenomenology of religious experience; the concept of the "numinous."</li>';
echo '<li><strong>Max Weber</strong> — <em>The Sociology of Religion</em> (1920). Religious traditions analyzed as social and economic forces.</li>';
echo '<li><strong>Émile Durkheim</strong> — <em>The Elementary Forms of Religious Life</em> (1912). Religion as social phenomenon.</li>';
echo '</ul>';
echo '<h2>Online Resources</h2>';
echo '<ul>';
echo '<li><strong>sacred-texts.com</strong> — free online library of sacred texts from all traditions</li>';
echo '<li><strong>Encyclopaedia Britannica</strong> — britannica.com — reliable reference for all religious traditions</li>';
echo '<li><strong>World Religion Database</strong> — academic subscription database</li>';
echo '<li><strong>BuddhaNet</strong> — buddhanet.net — Buddhist texts and resources</li>';
echo '<li><strong>Sefaria</strong> — sefaria.org — Jewish texts online in Hebrew and English</li>';
echo '<li><strong>Quran.com</strong> — Quran with multiple translations and audio</li>';
echo '</ul>';
echo '<div style="display:flex;gap:10px;flex-wrap:wrap;margin:24px 0 4px;">';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/religion/overview/">Full Map</a>';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/religion/people/">Founders</a>';
echo '</div></div>';
