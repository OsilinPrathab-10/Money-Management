import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import fs from 'fs';
import path from 'path';

// Helper function to get files recursively
function getFiles(dir, extensions, exclude = []) {
  const files = [];

  if (!fs.existsSync(dir)) return files;

  const items = fs.readdirSync(dir, { withFileTypes: true });

  for (const item of items) {
    const fullPath = path.join(dir, item.name);

    // Skip excluded patterns
    if (exclude.some(pattern => fullPath.includes(pattern))) continue;

    if (item.isDirectory()) {
      files.push(...getFiles(fullPath, extensions, exclude));
    } else if (item.isFile()) {
      const ext = path.extname(item.name);
      // Skip SCSS partials (files starting with _)
      if (extensions.includes(ext) && !item.name.startsWith('_')) {
        files.push(fullPath.replace(/\\/g, '/'));
      }
    }
  }

  return files;
}

// Get all vendor library files dynamically
const vendorLibsJs = getFiles('resources/assets/vendor/libs', ['.js'], ['node_modules']);
const vendorLibsScss = getFiles('resources/assets/vendor/libs', ['.scss'], ['node_modules', 'themes', '_tagify-inline-suggestion.scss', '_mixins.scss']);
const vendorLibsCss = getFiles('resources/assets/vendor/libs', ['.css'], ['node_modules']);

// Get all page-specific files
const pageScss = getFiles('resources/assets/vendor/scss/pages', ['.scss']);
const pageJs = getFiles('resources/assets/js', ['.js']);
const appJs = getFiles('resources/assets/custom-js', ['.js']);
const appCss = getFiles('resources/assets/custom-css', ['.css']);

// Get vendor core files
const vendorJs = fs.existsSync('resources/assets/vendor/js')
  ? fs.readdirSync('resources/assets/vendor/js')
    .filter(f => f.endsWith('.js'))
    .map(f => `resources/assets/vendor/js/${f}`)
  : [];

const vendorScss = getFiles('resources/assets/vendor/scss', ['.scss'], ['pages']);

// Get fonts
const vendorFonts = getFiles('resources/assets/vendor/fonts', ['.css', '.scss']);

export default defineConfig({
  plugins: [
    laravel({
      input: [
        // App CSS & JS
        'resources/css/app.css',
        'resources/assets/css/demo.css',
        'resources/assets/custom-js/app.js',

        // Critical vendor files (must be included explicitly)
        'resources/assets/vendor/libs/pickr/pickr-themes.scss',
        'resources/assets/vendor/libs/pickr/pickr.js',

        // Dynamically include all vendor libraries
        ...vendorLibsJs,
        ...vendorLibsScss,
        ...vendorLibsCss,

        // Vendor core files
        ...vendorJs,
        ...vendorScss,

        // Fonts
        ...vendorFonts,

        // Page specific files
        ...pageScss,
        ...pageJs,
        ...appJs,
        ...appCss,
      ],
      refresh: true,
    }),
  ],
});
