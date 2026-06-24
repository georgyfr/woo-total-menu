/**
 * Webpack config override for Woo Total Menu Builder.
 *
 * Default @wordpress/scripts looks in ./src/index.js, but we use ./builder/index.js
 * to keep the React source separate from the PHP src/ directory.
 *
 * The defaultConfig passed by @wordpress/scripts is a function
 * (env, argv) => config. We need to wrap and override its entry.
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

	// Override the default entry point.
	config.entry = {
		index: './builder/index.js',
	};

	return config;
};
