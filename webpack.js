const webpackConfig = require('@nextcloud/webpack-vue-config')
const path = require('path')
const { merge } = require('webpack-merge')

const config = {
	entry: {
		collections: path.join(__dirname, 'src', 'init-collections.js'),
		dashboard: path.join(__dirname, 'src', 'init-dashboard.js'),
		calendar: path.join(__dirname, 'src', 'init-calendar.js'),
		talk: path.join(__dirname, 'src', 'init-talk.js'),
	},
	output: {
		filename: '[name].js',
		jsonpFunction: 'webpackJsonpOCADeck',
		chunkFilename: '[name].js?v=[contenthash]',
	},
	resolve: {
		extensions: ['*', '.js', '.vue', '.json'],
		modules: [
			path.resolve(__dirname, 'node_modules'),
			'node_modules',
		],
	},
	stats: {
		context: path.resolve(__dirname, 'src'),
		assets: true,
		entrypoints: true,
		chunks: true,
		modules: true,
		excludeAssets: [/\.map/]
	}
}

module.exports = merge(webpackConfig, config)
