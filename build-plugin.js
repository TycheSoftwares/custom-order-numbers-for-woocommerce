const fs = require("fs");
const path = require("path");
const archiver = require("archiver");

const PLUGIN_SLUG = "custom-order-numbers-for-woocommerce";
const variant = process.argv.includes("--variant=wc") ? "wc" : "standard";
const DEST_BASE = path.join("dist", variant === "wc" ? "wc-version" : "standard-version");
const DEST = path.join(DEST_BASE, PLUGIN_SLUG);
const ZIP_PATH = path.join(DEST_BASE, `${PLUGIN_SLUG}.zip`);

// Files/folders to copy into the distribution.
const INCLUDE = [
    "build",
    "assets",
    "includes",
    "languages",
    `${PLUGIN_SLUG}.php`,
    "uninstall.php",
    "readme.txt",
    "changelog.txt",
];

if (!fs.existsSync("build")) {
    throw new Error("Missing /build — run `npm run build` first.");
}

// Clean and recreate the dist folder.
rmSync(DEST_BASE);
fs.mkdirSync(DEST, { recursive: true });

console.log(`\nPackaging ${variant === "wc" ? "WC Marketplace" : "Standard"} version…`);

// Copy all included files.
INCLUDE.forEach((item) => {
    const src = path.resolve(item);
    const dest = path.join(DEST, item);
    if (fs.existsSync(src)) {
        copySync(src, dest);
    } else {
        console.warn(`  ⚠  Skipped missing: ${item}`);
    }
});

// Apply WC Marketplace modifications to the copied files.
if (variant === "wc") {
    console.log("  Applying WC Marketplace modifications…");
    patchWcVersion(DEST);
}

// Create ZIP.
const output = fs.createWriteStream(ZIP_PATH);
const archive = archiver("zip", { zlib: { level: 9 } });

archive.pipe(output);
archive.directory(DEST, PLUGIN_SLUG);
archive.finalize();

output.on("close", () => {
    const kb = Math.round(archive.pointer() / 1024);
    console.log(`  ✓ ZIP created: ${ZIP_PATH} (${kb} KB)\n`);
});

// ---------------------------------------------------------------------------
// WC Marketplace patch functions
// ---------------------------------------------------------------------------

function patchWcVersion(dest) {
    // Remove Tyche class files.
    const filesToRemove = [
        "includes/class-tyche-con-tracking.php",
        "includes/class-tyche-con-deactivation.php",
    ];

    filesToRemove.forEach((file) => {
        const filePath = path.join(dest, file);
        if (fs.existsSync(filePath)) {
            fs.rmSync(filePath, { force: true });
            console.log(`  ✓ Removed ${file}`);
        } else {
            console.warn(`  ⚠  Skipped missing: ${file}`);
        }
    });

    // Remove includes/tyche folder.
    const tycheDir = path.join(dest, "includes/tyche");
    if (fs.existsSync(tycheDir)) {
        fs.rmSync(tycheDir, { recursive: true, force: true });
        console.log("  ✓ Removed includes/tyche/");
    } else {
        console.warn("  ⚠  Skipped missing: includes/tyche/");
    }

    patchFilesPhp(path.join(dest, "includes/class-files.php"));
    patchAdminApiSettingsPhp(path.join(dest, "includes/api/class-admin-api-settings.php"));
    patchAdminScriptsPhp(path.join(dest, "includes/admin/class-admin-scripts.php"));
}

// Injects isWcVariant => true into the wp_localize_script data in class-admin-scripts.php
// so the JS hides the License tab and Usage Data section at runtime.
function patchAdminScriptsPhp(file) {
    let content = fs.readFileSync(file, "utf8");

    content = replaceOrWarn(
        content,
        "\t\t\t\tarray(\n" +
        "\t\t\t\t\t'paymentGateways'  => CON_Functions::get_payment_gateways(),\n" +
        "\t\t\t\t\t'userRoles'        => $user_roles,\n" +
        "\t\t\t\t\t'currentUserRoles' => (array) $user->roles,\n" +
        "\t\t\t\t)",
        "\t\t\t\tarray(\n" +
        "\t\t\t\t\t'paymentGateways'  => CON_Functions::get_payment_gateways(),\n" +
        "\t\t\t\t\t'userRoles'        => $user_roles,\n" +
        "\t\t\t\t\t'currentUserRoles' => (array) $user->roles,\n" +
        "\t\t\t\t\t'isWcVariant'      => true,\n" +
        "\t\t\t\t)",
        file,
        "conAdminData array"
    );

    fs.writeFileSync(file, content);
}

// Removes the plugin-license include line from class-files.php.
function patchFilesPhp(file) {
    let content = fs.readFileSync(file, "utf8");

    content = replaceOrWarn(
        content,
        "\n\t\tCON()::include_file( 'tyche/components/plugin-license/class-tyche-license-api.php' );\n",
        "\n",
        file,
        "plugin-license include"
    );

    fs.writeFileSync(file, content);
}

// Removes the Tyche_Plugin_Tracking use statement, reset-tracking endpoint,
// and reset_tracking() method from class-admin-api-settings.php.
function patchAdminApiSettingsPhp(file) {
    let content = fs.readFileSync(file, "utf8");

    // 1. Remove the use statement.
    content = replaceOrWarn(
        content,
        "\nuse Tyche\\CON\\Tyche_Plugin_Tracking;\n",
        "\n",
        file,
        "use Tyche_Plugin_Tracking"
    );

    // 2. Remove the reset-tracking route registration block.
    content = replaceOrWarn(
        content,
        "\n\t\t// Reset tracking.\n" +
        "\t\tregister_rest_route(\n" +
        "\t\t\tself::$base_endpoint,\n" +
        "\t\t\t'reset-tracking',\n" +
        "\t\t\tarray(\n" +
        "\t\t\t\tarray(\n" +
        "\t\t\t\t\t'methods'             => \\WP_REST_Server::CREATABLE,\n" +
        "\t\t\t\t\t'callback'            => array( __CLASS__, 'reset_tracking' ),\n" +
        "\t\t\t\t\t'permission_callback' => array( __CLASS__, 'get_permission' ),\n" +
        "\t\t\t\t),\n" +
        "\t\t\t)\n" +
        "\t\t);\n",
        "",
        file,
        "reset-tracking route"
    );

    // 3. Remove the reset_tracking() method.
    content = replaceOrWarn(
        content,
        "\n\t/**\n" +
        "\t * Resets usage tracking by deleting the tracking options.\n" +
        "\t */\n" +
        "\tpublic static function reset_tracking( $request ) {\n" +
        "\t\tTyche_Plugin_Tracking::reset_tracker_setting( 'con' );\n" +
        "\t\treturn self::return_response( array( 'message' => 'Tracking has been successfully reset.' ) );\n" +
        "\t}\n",
        "",
        file,
        "reset_tracking() method"
    );

    fs.writeFileSync(file, content);
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function replaceOrWarn(content, search, replacement, file, label) {
    if (!content.includes(search)) {
        console.warn(`  ⚠  ${path.basename(file)}: "${label}" marker not found — patch skipped`);
        return content;
    }
    return content.replace(search, replacement);
}

function copySync(src, dest) {
    const stat = fs.statSync(src);
    if (stat.isDirectory()) {
        fs.mkdirSync(dest, { recursive: true });
        fs.readdirSync(src).forEach((child) => {
            copySync(path.join(src, child), path.join(dest, child));
        });
    } else {
        fs.mkdirSync(path.dirname(dest), { recursive: true });
        fs.copyFileSync(src, dest);
    }
}

function rmSync(dir) {
    if (fs.existsSync(dir)) {
        fs.rmSync(dir, { recursive: true, force: true });
    }
}
