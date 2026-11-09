/**
 * Release packaging:  npm run release  →  release/terax-ai-assistant-<version>.zip
 * Only runtime files are shipped (no sources, node_modules, source maps).
 *
 * WordPress.org build:  npm run release:wporg  →  release/wporg/terax-ai-assistant-<version>.zip (and the folder release/wporg/terax-ai-assistant/)
 * Same files as the normal release. The free plugin has no updater, license code or TERAX server calls (they live in the Pro add-on, folder pro/).
 */
const loadGruntTasks = require("load-grunt-tasks");

module.exports = function (grunt) {
  const pkg = grunt.file.readJSON("package.json");

  const distFiles = [
    "terax-ai-assistant.php",
    "plugin.php",
    "uninstall.php",
    "readme.txt",
    "LICENSE",
    "config/**",
    "database/**",
    "includes/**",
    "libs/**",
    "languages/**",
    // No runtime Composer packages: the built-in PSR-4 autoloader replaces vendor/, so release needs no Composer.
    "autoload-fallback.php",
    "assets/admin/dist/**",
    "assets/frontend/dist/**",
    "!**/*.map",
  ];

  // The Pro add-on (folder pro/) has its own version, read from its plugin header.
  const proVersion = (/^[ \t]*\*[ \t]*Version:[ \t]*(\S+)/m.exec(grunt.file.read("pro/terax-ai-assistant-pro.php")) || [])[1];

  grunt.initConfig({
    clean: { release: ["release/"], wporg: ["release/wporg/"] },
    compress: {
      main: {
        options: { mode: "zip", archive: `release/terax-ai-assistant-${pkg.version}.zip` },
        expand: true,
        src: distFiles,
        dest: "terax-ai-assistant/",
      },
      pro: {
        options: { mode: "zip", archive: `release/pro/terax-ai-assistant-pro-${proVersion}.zip` },
        expand: true,
        cwd: "pro/",
        src: ["terax-ai-assistant-pro.php", "includes/**", "config/**", "LICENSE", "CHANGELOG.md"],
        dest: "terax-ai-assistant-pro/",
      },
    },
    checktextdomain: {
      options: {
        text_domain: "terax-ai-assistant",
        correct_domain: false,
        keywords: [
          "__:1,2d",
          "_e:1,2d",
          "_x:1,2c,3d",
          "esc_html__:1,2d",
          "esc_html_e:1,2d",
          "esc_attr__:1,2d",
          "esc_attr_e:1,2d",
          "_n:1,2,4d",
        ],
      },
      files: { src: ["includes/**/*.php", "plugin.php", "terax-ai-assistant.php"], expand: true },
    },
  });

  loadGruntTasks(grunt);
  grunt.registerTask("release", ["clean:release", "compress:main"]);
  // Pro add-on ZIP: release/pro/terax-ai-assistant-pro-<version>.zip (in its own folder so `release/*.zip` stays the free plugin).
  grunt.registerTask("release-pro", ["compress:pro"]);

  // WordPress.org build: copy the shipped files into a folder, then zip that folder.
  const wporgFiles = distFiles;
  grunt.registerTask("wporg-dir", function () {
    const dest = "release/wporg/terax-ai-assistant/";
    const files = grunt.file.expand({ filter: "isFile" }, wporgFiles);
    files.forEach((f) => grunt.file.copy(f, dest + f));
    grunt.log.ok(`${files.length} files copied to ${dest}`);
  });
  grunt.config.set("compress.wporg", {
    options: { mode: "zip", archive: `release/wporg/terax-ai-assistant-${pkg.version}.zip` },
    expand: true,
    cwd: "release/wporg/",
    src: ["terax-ai-assistant/**"],
  });
  grunt.registerTask("release-wporg", ["clean:wporg", "wporg-dir", "compress:wporg"]);
  grunt.registerTask("i18n-check", ["checktextdomain"]);
};
