<?php
declare(strict_types=1);
echo '<div class="mk-prose">';
echo '<p class="mk-muted" style="margin-top:0;">Igbo traditional norms, rites, and communal frameworks — with comparative analysis across African and world traditions.</p>';
echo '<h2>What is Tradition?</h2>';
echo '<p>In Igbo life, tradition — <em>omenala</em> or <em>ọdinala</em> — is not merely custom. It is the accumulated moral, social, and spiritual wisdom of generations, encoded in law, ritual, proverb, and communal practice. It governs how people relate to each other, to the land, to the ancestors, and to the forces of the spirit world. It is simultaneously descriptive (this is how things are) and prescriptive (this is how things should be) and aspirational (this is the standard against which we measure ourselves).</p>';
echo '<p>Igbo tradition is not static. It has always been adaptive — absorbing new influences while maintaining core values. What makes it "tradition" is not its unchangingness but its rootedness: the reference back to what was established by the ancestors (ọdịnaala — "what is on the ground") as the foundation for what is done today.</p>';
echo '<h2>The Architecture of Igbo Tradition</h2>';
echo '<ul>';
echo '<li><strong>Ọdinala</strong> — the spiritual and cosmological framework (covered in depth in the Religion and Esoterism subjects)</li>';
echo '<li><strong>Omenani</strong> — the social customs and norms: greetings, hospitality, kinship obligations, market behavior, dispute resolution</li>';
echo '<li><strong>Ọlụ oji</strong> — the kola nut tradition: the most important single ritual in Igbo social life</li>';
echo '<li><strong>Ichu ọfọ</strong> — the title and authority system: how leadership and respect are earned and maintained</li>';
echo '<li><strong>Ọzọ and Nze</strong> — the title societies that structure adult male social life</li>';
echo '<li><strong>Ụmụnna and Ụmụada</strong> — the patrilineage and daughters of the lineage: the two primary kinship institutions</li>';
echo '<li><strong>Age grades (Otu ọgbọ)</strong> — the peer cohort system structuring communal labor, social obligation, and political voice</li>';
echo '<li><strong>Masquerade (Mmanwu)</strong> — the ancestral spirit tradition: the most visible and complex institution of Igbo communal life</li>';
echo '</ul>';
echo '<h2>Navigate This Subject</h2>';
echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;margin:16px 0;">';
$sections = [
  ['/subjects/tradition/overview/','⚖️ Overview','The structure of Igbo traditional life — institutions, values, and frameworks'],
  ['/subjects/tradition/topics/','🥜 Key Traditions','Kola nut, age grades, title systems, masquerades, festivals, and markets'],
  ['/subjects/tradition/people/','👤 Tradition Keepers','Elders, titled men and women, dibias, and cultural custodians'],
  ['/subjects/tradition/sources/','📚 Sources','Texts, oral literature, and references for Igbo tradition'],
];
foreach($sections as [$href,$title,$desc]) {
  echo '<a href="'.$href.'" style="display:block;padding:14px;border:1px solid #e5e7eb;border-radius:12px;text-decoration:none;color:inherit;background:#fff;">';
  echo '<div style="font-weight:800;font-size:.95rem;color:#111;margin-bottom:4px;">'.$title.'</div>';
  echo '<div style="font-size:.82rem;color:#6b7280;line-height:1.4;">'.$desc.'</div>';
  echo '</a>';
}
echo '</div>';
echo '<div style="display:flex;gap:10px;flex-wrap:wrap;margin:18px 0 4px;">';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/religion/african/">→ Ọdinala Religion</a>';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/esoterism/african/">→ Igbo Esoteric</a>';
echo '<a class="mk-btn mk-btn--ghost" href="/subjects/culture/intro/">→ Igbo Culture</a>';
echo '</div></div>';
