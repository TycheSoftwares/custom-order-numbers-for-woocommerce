<?php
/**
 * Custom Order Numbers for WooCommerce - Admin Files Class
 *
 * Class for including files for the Admin.
 *
 * @author      Tyche Softwares
 * @package     CON/Admin/Files
 * @category    Classes
 * @since       2.0
 */

namespace Tyche\CON;

defined( 'ABSPATH' ) || exit;

/**
 * CON Admin Files.
 *
 * @since 1.0
 */
class Files {

	/**
	 * Include files.
	 *
	 * @since 1.0
	 */
	public static function include_files() {

        CON()::include_file( 'api/class-admin-api.php' );
        CON()::include_file( 'api/class-admin-api-settings.php' );

        $tyche_files = array(
            'class-tyche-con-tracking.php',
            'class-tyche-con-deactivation.php',
        );

        foreach ( $tyche_files as $tyche_file ) {
            if ( file_exists( CON_PLUGIN_DIR_PATH . '/includes/' . $tyche_file ) ) {
                CON()::include_file( $tyche_file );
            }
        }

		CON()::include_file( 'class-functions.php' );

		CON()::include_file( 'admin/class-admin.php' );

		// Scripts.
		CON()::include_file( 'admin/class-admin-scripts.php' );
		new \Tyche\CON\Admin\Admin_Scripts();

		//Frontend
		CON()::include_file( 'class-core.php' );
	}

	/**
	 * Loads Dependency Files.
	 * If there are required files needed ( to be included before ) for the execution of the view file, those dependencies can be added here.
	 *
	 * @param string $section Section Directory.
	 * @param string $filename File in the section Directory to be loaded.
	 * @since 5.19.0
	 */
	public static function load_dependencies( $section, $filename ) {

		if ( '' === $section || '' === $filename ) {
			return;
		}
	}
}