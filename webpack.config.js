/**
 * Webpack config override for Woo Total Menu.
 *
 * Two entry points:
 *   - index  → ./builder/index.js  (React Builder UI)
 *   - blocks → ./blocks/index.js   (Gutenberg editor blocks)
 *
 * The defaultConfig passed by @wordpress/scripts is a function
 * (env, argv) => config. We wrap and override its entry.
 *
 * @param {object} env Webpack environment.
 * @param {object} argv Webpack args.
 * @returns {object} Webpack config.
 */
const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = (env, argv) => {
	// Get the default config from @wordpress/scripts.
	const config = typeof defaultConfig === 'function'
		? defaultConfig(env, argv)
		: { ...defaultConfig };

	// Override the default entry point with two entries.
	config.entry = {
		index: './builder/index.js',
		blocks: './blocks/index.js',
	};

	return config;
};
