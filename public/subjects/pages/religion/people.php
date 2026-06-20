<?php
declare(strict_types=1);
echo '<div class="mk-prose">';
echo '<p class="mk-muted" style="margin-top:0;">Founders, prophets, teachers, and reformers across all religious traditions — chronologically ordered.</p>';

$founders = [
  ['c. 1800 BCE','Abraham','Judaism, Christianity, Islam','Patriarch called by God to leave Mesopotamia; founding figure of all three Abrahamic faiths through his covenant with God. Father of Isaac (through Sarah, ancestor of Jews and Christians) and Ishmael (through Hagar, ancestor of Arabs and by tradition Muslims).'],
  ['c. 1500–1300 BCE','Zarathustra (Zoroaster)','Zoroastrianism','Ancient Iranian prophet who received visions of Ahura Mazda (the Wise Lord) and proclaimed a monotheistic reform of Iranian polytheism. His concepts of heaven/hell, cosmic dualism, and final judgment profoundly influenced Judaism, Christianity, and Islam.'],
  ['c. 1300 BCE','Moses (Musa)','Judaism','Prophet and lawgiver who led the Israelites out of Egyptian slavery and received the Torah from God at Mount Sinai. The founding figure of Judaism as a covenant religion.'],
  ['c. 1000 BCE','King David','Judaism','Second king of Israel; psalmist; established Jerusalem as the holy city. His lineage is the origin of the Messianic expectation.'],
  ['c. 600–500 BCE','Mahavira (Vardhamana)','Jainism','24th Tirthankara (ford-maker) of Jainism; taught the path of non-violence (ahimsa), non-attachment, and austerity. A contemporary of the Buddha.'],
  ['c. 563–483 BCE','Siddhartha Gautama (the Buddha)','Buddhism','Born a prince in Lumbini (Nepal), renounced luxury to seek liberation, achieved enlightenment under the Bodhi tree, and spent 45 years teaching the Dharma. Founder of Buddhism.'],
  ['c. 551–479 BCE','Kong Qiu (Confucius)','Confucianism','Chinese philosopher and teacher from the state of Lu. Devoted his life to studying and transmitting classical Chinese values — benevolence (ren), righteousness (yi), and ritual propriety (li). His Analects are the foundation of Confucianism.'],
  ['c. 500 BCE','Laozi','Taoism','Legendary Chinese sage attributed with writing the Tao Te Ching — the foundational text of philosophical Taoism. His historical existence is uncertain.'],
  ['c. 372–289 BCE','Mencius (Mengzi)','Confucianism','The "Second Sage" of Confucianism; argued that human nature is inherently good; developed Confucian political theory including the right to overthrow unjust rulers.'],
  ['c. 369–286 BCE','Zhuangzi','Taoism','Chinese philosopher who developed Taoist thought in a more playful, paradoxical direction; his writings are among the greatest works of Chinese literature.'],
  ['c. 105–43 BCE','Julius Caesar (deified)','Roman Religion','As an example of ruler deification — a widespread ancient practice. Roman emperors from Augustus onward were deified after (and sometimes before) death.'],
  ['c. 4 BCE – 30 CE','Jesus of Nazareth','Christianity','Jewish teacher and preacher from Galilee, believed by Christians to be the Son of God, Messiah, and savior. Crucified under Pontius Pilate; his resurrection is the foundation of Christian faith.'],
  ['c. 10–67 CE','Paul of Tarsus','Christianity','The apostle who transformed Jesus\'s Jewish movement into a universal religion. His letters form the theological foundation of Christianity; he established communities across the Mediterranean world.'],
  ['570–632 CE','Muhammad ibn Abdullah','Islam','The final prophet of Islam; received the Quran through the angel Jibril over 23 years; unified Arabia under Islam; founder of the Islamic community (Umma).'],
  ['c. 700–750 CE','Adi Shankaracharya','Hinduism','Indian philosopher who systematized Advaita Vedanta (non-dualism) and revitalized Hinduism in competition with Buddhism. Founded four monastic centers across India.'],
  ['1469–1539 CE','Guru Nanak Dev Ji','Sikhism','Born in Punjab; received divine commission at age 30; declared "There is no Hindu, there is no Muslim"; traveled across Asia teaching the oneness of God and human equality.'],
  ['1483–1546 CE','Martin Luther','Protestant Christianity','German monk who sparked the Protestant Reformation with his 95 Theses (1517); challenged papal authority; translated the Bible into German; founded Lutheranism.'],
  ['1509–1564 CE','John Calvin','Reformed Christianity','French theologian who developed Reformed/Calvinist theology; his <em>Institutes of the Christian Religion</em> is the foundational text of Presbyterianism and Reformed churches.'],
  ['1638–1715 CE','Guru Gobind Singh','Sikhism','The tenth and final human Guru; established the Khalsa (community of the pure); created the Five Ks; declared the Guru Granth Sahib as the eternal, living Guru.'],
  ['1703–1791 CE','John Wesley','Methodism','Anglican priest who founded the Methodist movement; his emphasis on personal holiness, social justice, and itinerant preaching transformed Protestant Christianity.'],
  ['1712–1792 CE','Muhammad ibn Abd al-Wahhab','Wahhabi/Salafi Islam','Arabian scholar who formed an alliance with Muhammad ibn Saud; advocated return to "pure" early Islam; his teachings became the basis of Saudi Arabian state religion.'],
  ['1804–1869 CE','Allan Kardec','Spiritism','French educator who compiled spirit communications into a systematic philosophy; founder of Spiritism (Kardecism); enormously influential in Brazil.'],
  ['1817–1892 CE','Baháʼu\'lláh','Baháʼí Faith','Iranian nobleman who declared himself the promised one foretold by the Báb; spent his life as a prisoner and exile; wrote the core texts of the Baháʼí Faith.'],
  ['1819–1850 CE','The Báb','Baháʼí Faith','Siyyid ʻAlí-Muḥammad of Shiraz; declared himself the "Gate" to a coming divine manifestation; executed in 1850; forerunner of Baháʼu\'lláh.'],
  ['1830–1905 CE','Mary Baker Eddy','Christian Science','Founded Christian Science in 1866 after claiming miraculous healing; wrote Science and Health with Key to the Scriptures.'],
  ['1863–1902 CE','Swami Vivekananda','Neo-Vedanta / Hindu Reform','Indian monk who brought Hinduism to the West at the 1893 Parliament of World Religions; founded the Ramakrishna Mission; made Vedanta a global philosophy.'],
  ['1869–1937 CE','Marcus Garvey','Pan-Africanism / Rastafari precursor','Jamaican Black nationalist whose prophecy ("Look to Africa for the crowning of a Black King") inspired the Rastafari movement when Haile Selassie was crowned in 1930.'],
  ['1875–1947 CE','Aleister Crowley','Thelema / Western Occultism','English occultist who founded Thelema; declared "Do what thou wilt shall be the whole of the Law"; influenced modern occultism, Wicca, and New Age movements enormously.'],
  ['1884–1964 CE','Gerald Gardner','Wicca','British civil servant who publicly founded Wicca in 1954; claimed initiation into a surviving witch cult; his books created the modern Wicca movement.'],
  ['1892–1975 CE','Haile Selassie I','Rastafari (as divine figure)','Emperor of Ethiopia; identified by Rastafarians as the returned Messiah and the fulfillment of Revelation 5:5; the Lion of the Tribe of Judah.'],
  ['1905–1970 CE','Paul Twitchell','Eckankar','American writer who founded Eckankar in 1965; claimed to have been initiated by a succession of ECK Masters; introduced the concepts of Soul Travel and the Sound Current.'],
  ['1926–2009 CE','Ngô Văn Chiêu','Cao Dai','Vietnamese official credited with founding Cao Dai in 1926 after receiving divine communications; the religion synthesizes all world faiths under one God.'],
  ['1929–1968 CE','Martin Luther King Jr.','Social Gospel / Christianity','Baptist minister who led the American civil rights movement; applied Christian theology to the struggle for racial justice; his "I Have a Dream" speech is among the most important religious-political addresses of the 20th century.'],
];

echo '<table style="width:100%;border-collapse:collapse;font-size:.88rem;">';
echo '<tr style="border-bottom:2px solid #e5e7eb;"><th style="text-align:left;padding:8px 10px;">Period</th><th style="text-align:left;padding:8px 10px;">Figure</th><th style="text-align:left;padding:8px 10px;">Tradition</th><th style="text-align:left;padding:8px 10px;">Significance</th></tr>';
foreach($founders as [$date,$name,$tradition,$desc]) {
  echo '<tr style="border-bottom:1px solid #f0f0f0;vertical-align:top;">';
  echo '<td style="padding:10px;color:#888;white-space:nowrap;font-size:.82rem;">'.$date.'</td>';
  echo '<td style="padding:10px;font-weight:800;color:#111;white-space:nowrap;">'.$name.'</td>';
  echo '<td style="padding:10px;color:#2b6cb0;font-size:.82rem;">'.$tradition.'</td>';
  echo '<td style="padding:10px;color:#374151;line-height:1.5;">'.$desc.'</td>';
  echo '</tr>';
}
echo '</table>';
echo '<div style="display:flex;gap:10px;flex-wrap:wrap;margin:24px 0 4px;">';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/religion/overview/">Full Map</a>';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/religion/topics/">Ancient</a>';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/religion/eastern/">Eastern</a>';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/religion/abrahamic/">Abrahamic</a>';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/religion/african/">African</a>';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/religion/modern/">Modern</a>';
echo '</div></div>';
