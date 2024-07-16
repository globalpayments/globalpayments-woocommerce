const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const WooCommerceDependencyExtractionWebpackPlugin = require('@woocommerce/dependency-extraction-webpack-plugin');
const path = require('path');

const wcDepMap = {
	'@woocommerce/blocks-registry': ['wc', 'wcBlocksRegistry'],
	'@woocommerce/settings'       : ['wc', 'wcSettings']
};

const wcHandleMap = {
	'@woocommerce/blocks-registry': 'wc-blocks-registry',
	'@woocommerce/settings'       : 'wc-settings'
};

const requestToExternal = (request) => {
	if (wcDepMap[request]) {
		return wcDepMap[request];
	}
};

const requestToHandle = (request) => {
	if (wcHandleMap[request]) {
		return wcHandleMap[request];
	}
};

module.exports = {
	...defaultConfig,
	entry: {
		'gateways': '/resources/js/frontend/gateways/index.js',
	},
	output: {
		path: path.resolve( __dirname, 'assets/frontend/blocks' ),
		filename: '[name].js',
	},
	plugins: [
		new WooCommerceDependencyExtractionWebpackPlugin({
			injectPolyfill: true,
			requestToExternal,
			requestToHandle
		})
	]
}
