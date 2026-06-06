// Replace Tailwind Play CDN block with static built CSS link in all HTML files
const fs = require('fs');
const path = require('path');

const dir = __dirname.replace(/\\/g, '/');
const files = fs.readdirSync(dir).filter(f => f.endsWith('.html') && f !== 'index.html');

let replaced = 0;
for (const f of files) {
  const full = path.join(dir, f);
  let content = fs.readFileSync(full, 'utf8');
  // Match the script src + tailwind.config block
  const re = /<script src="assets\/tailwind\.js"[^>]*><\/script>\s*<script>[\s\S]*?<\/script>/;
  if (re.test(content)) {
    content = content.replace(re, '<link rel="stylesheet" href="assets/tailwind-built.css">');
    fs.writeFileSync(full, content, 'utf8');
    console.log('replaced: ' + f);
    replaced++;
  } else {
    console.log('skipped (no match): ' + f);
  }
}
console.log('total: ' + replaced);
