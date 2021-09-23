module.exports = function( grunt ) {

	'use strict';

	// Project configuration
	grunt.initConfig( {

		pkg: grunt.file.readJSON( 'package.json' ),

		addtextdomain: {
			options: {
				textdomain: 'trustedlogin-vendor',
			},
			update_all_domains: {
				options: {
					updateDomains: true
				},
				src: [ '*.php', '**/*.php', '!\.git/**/*', '!bin/**/*', '!node_modules/**/*', '!tests/**/*' ]
			}
		},

		makepot: {
			target: {
				options: {
					domainPath: '/languages',
					exclude: [ '\.git/*', 'bin/*', 'node_modules/*', 'tests/*' ],
					mainFile: 'trustedlogin-vendor.php',
					potFilename: 'trustedlogin-vendor.pot',
					potHeaders: {
						poedit: true,
						'x-poedit-keywordslist': true
					},
					type: 'wp-plugin',
					updateTimestamp: true
				}
			}
		},

		// Pull in the latest translations
		exec: {
			transifex: 'tx pull -a --parallel',

			// Create a ZIP file
			zip: {
				cmd: function( version = '' ) {

					var filename = ( version === '' ) ? 'trustedlogin-vendor' : 'trustedlogin-vendor-' + version;

					// First, create the full archive
					var command = 'git-archive-all trustedlogin-vendor.zip &&';

					command += 'unzip -o trustedlogin-vendor.zip &&';

					command += 'zip -r ../' + filename + '.zip trustedlogin-vendor &&';

					command += 'rm -rf trustedlogin-vendor/ && rm -f trustedlogin-vendor.zip';

					return command;
				}
			}
		},
	} );

	grunt.loadNpmTasks( 'grunt-wp-i18n' );
	grunt.loadNpmTasks( 'grunt-exec' );
	grunt.registerTask( 'default', [ 'i18n' ] );
	grunt.registerTask( 'i18n', ['addtextdomain', 'makepot'] );

	grunt.util.linefeed = '\n';

};
