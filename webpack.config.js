const path = require('path');
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const OptimizeCSSAssetsPlugin = require("optimize-css-assets-webpack-plugin");
const TerserPlugin = require('terser-webpack-plugin');
const BrowserSyncPlugin = require('browser-sync-webpack-plugin');
const autoprefixer = require('autoprefixer');
const VueLoaderPlugin = require('vue-loader/lib/plugin');

const config = require('./config.json');

let plugins = [];
let entryPoints = {
    frontend: [
        "./assets/scss/style.scss",
        "./assets/src/public/form.js",
        "./assets/src/public/modal.js",
    ],
    admin: [
        "./assets/scss/admin.scss",
        "./assets/src/admin/admin.js",
        "./assets/src/admin/main.js",
        "./assets/src/admin/settings/main.js",
        "./assets/src/admin/forms/main.js",
    ],
    'gutenberg-block': [
        "./assets/scss/gutenberg-block.scss",
        "./assets/src/gutenberg/gutenberg-block.js"
    ],
    'select2': [
        "./assets/scss/select2.scss",
    ],
    polyfill: [
        "./assets/src/polyfill/classList.js",
        "./assets/src/polyfill/validityState.js",
    ],
};

plugins.push(new MiniCssExtractPlugin({
    filename: "../css/[name].css"
}));

plugins.push(new BrowserSyncPlugin({
    proxy: config.proxyURL
}));

plugins.push(new VueLoaderPlugin());

module.exports = (env, argv) => {
    let isDev = argv.mode !== 'production';

    return {
        "entry": entryPoints,
        "output": {
            "path": path.resolve(__dirname, 'assets/js'),
            "filename": '[name].js'
        },
        "devtool": isDev ? 'eval-source-map' : false,
        "module": {
            "rules": [
                {
                    "test": /\.js$/,
                    "use": {
                        "loader": "babel-loader",
                        "options": {
                            presets: ['@babel/preset-env']
                        }
                    }
                },
                {
                    test: /\.vue$/,
                    use: [
                        {loader: 'vue-loader'}
                    ]
                },
                {
                    test: /\.(sass|scss)$/,
                    use: [
                        {
                            loader: isDev ? "vue-style-loader" : MiniCssExtractPlugin.loader
                        },
                        {
                            loader: "css-loader",
                            options: {
                                sourceMap: isDev,
                                importLoaders: 1
                            }
                        },
                        {
                            loader: "postcss-loader",
                            options: {
                                sourceMap: isDev,
                                plugins: () => [autoprefixer()],
                            },
                        },
                        {
                            loader: "sass-loader",
                            options: {
                                sourceMap: isDev,
                            },
                        }
                    ]
                },
                {
                    test: /\.(png|je?pg|gif|svg|eot|ttf|woff|woff2)$/,
                    use: [
                        {loader: 'file-loader'},
                    ],
                },
            ]
        },
        optimization: {
            minimizer: [
                new TerserPlugin(),
                new OptimizeCSSAssetsPlugin()
            ]
        },
        resolve: {
            alias: {
                'vue$': 'vue/dist/vue.esm.js',
                '@': path.resolve('./assets/src/'),
            },
            modules: [
                path.resolve('./node_modules'),
                path.resolve(path.join(__dirname, 'assets/src/')),
                path.resolve(path.join(__dirname, 'assets/src/shapla')),
            ],
            extensions: ['*', '.js', '.vue', '.json']
        },
        "plugins": plugins
    }
};
