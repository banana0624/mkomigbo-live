// registry.js
const subjects = {
  history: { id: "history", slug: "history", name: "History" },
  slavery: { id: "slavery", slug: "slavery", name: "Slavery" },
  people: { id: "people", slug: "people", name: "People" },
  culture: { id: "culture", slug: "culture", name: "Culture" },
  religion: { id: "religion", slug: "religion", name: "Religion" }
  // add more subjects here...
};

const pages = [
  {
    id: "history.timeline.001",
    subjectId: "history",
    slug: "timeline",
    title: "A Timeline of Mkomigbo History",
    status: "published"
  },
  {
    id: "slavery.overview.001",
    subjectId: "slavery",
    slug: "overview",
    title: "Slavery Overview",
    status: "draft"
  }
];

// Helper: get all pages for a subject
function getPagesBySubject(subjectId) {
  return pages.filter(p => p.subjectId === subjectId);
}

// Validation
(function validateRegistry() {
  const ids = new Set();
  pages.forEach(p => {
    if (ids.has(p.id)) {
      console.error("Duplicate page id:", p.id);
    }
    ids.add(p.id);
    if (!subjects[p.subjectId]) {
      console.error("Missing subject for page:", p.id);
    }
  });
})();

// --- Registry Validation Snippet ---
// Ensures every subject entry has required fields: id, title, and path.

(function validateRegistry(registry) {
  if (!Array.isArray(registry)) {
    console.error("Registry must be an array of subjects.");
    return;
  }

  registry.forEach((entry, index) => {
    const missing = [];
    if (!entry.id) missing.push("id");
    if (!entry.title) missing.push("title");
    if (!entry.path) missing.push("path");

    if (missing.length > 0) {
      console.error(
        `Registry entry at index ${index} is missing: ${missing.join(", ")}`
      );
    }
  });

  console.log("Registry validation complete.");
})(window.registry || []);

