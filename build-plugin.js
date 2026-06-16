const fs = require("fs");
const path = require("path");
const archiver = require("archiver");

const PLUGIN_SLUG = "custom-order-numbers-for-woocommerce";
const variant = process.argv.includes("--variant=wc")
    ? "wc"
    : process.argv.includes("--variant=svn")
        ? "svn"
        : "standard";

// SVN places the plugin folder directly inside dist/ (no version subfolder).
const DEST_BASE = variant === "svn"
    ? "dist"
    : path.join("dist", variant === "wc" ? "wc-version" : "standard-version");
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

// Clean and recreate the destination.
// For SVN, only wipe the plugin subfolder so other dist variants are preserved.
rmSync(variant === "svn" ? DEST : DEST_BASE);
fs.mkdirSync(DEST, { recursive: true });

const variantLabel = variant === "wc" ? "WC Marketplace" : variant === "svn" ? "SVN" : "Standard";
console.log(`\nPackaging ${variantLabel} version…`);

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
        "\t\t\t\t\t'upgradeUrl'       => 'https://www.tychesoftwares.com/products/woocommerce-custom-order-numbers-plugin?utm_source=conupgradetopro&utm_medium=link&utm_campaign=CustomOrderNumbersLite',\n" +
        "\t\t\t\t)",
        "\t\t\t\tarray(\n" +
        "\t\t\t\t\t'paymentGateways'  => CON_Functions::get_payment_gateways(),\n" +
        "\t\t\t\t\t'userRoles'        => $user_roles,\n" +
        "\t\t\t\t\t'currentUserRoles' => (array) $user->roles,\n" +
        "\t\t\t\t\t'upgradeUrl'       => 'https://www.tychesoftwares.com/products/woocommerce-custom-order-numbers-plugin?utm_source=conupgradetopro&utm_medium=link&utm_campaign=CustomOrderNumbersLite',\n" +
        "\t\t\t\t\t'isWcVariant'      => true,\n" +
        "\t\t\t\t)",
        file,
        "conAdminData array"
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
