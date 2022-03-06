const webpack               = require('webpack');
const path                  = require('path');
const CopyPlugin            = require('copy-webpack-plugin');
const MiniCssExtractPlugin  = require('mini-css-extract-plugin');

module.exports = [
  {
    externals: {
      jquery:       'jQuery',
      kiss:         'kiss',
      FontAwesome:  'kiss.fontAwesome',
    },
    entry: './src/app.js',
    devtool: 'source-map',
    output: {
      filename:       'app.js',
      chunkFilename:  'app.[name].js',
      path:           path.resolve(__dirname, './public/dist'),
      publicPath:     '/dist/',
      library:        'app'
    },
    module: {
      rules: [
        {
          test: /\.m?js$/,
          exclude: /node_modules/,
          use:  {
            loader: 'babel-loader', 
            options: {                
              presets: ['@babel/preset-env'],
              plugins: [
                "@babel/plugin-proposal-class-properties",
                "@babel/plugin-proposal-private-methods",
                '@babel/plugin-transform-runtime'
              ]
            },
          }
        },
        {
          test: /\.s?[ac]ss$/i,
          use: [
            MiniCssExtractPlugin.loader,
            { loader: 'css-loader' },
            { loader: 'sass-loader', options: { sourceMap: true } },
          ]
        },
        {         
          test: /\.(woff|woff2|eot|ttf|otf|svg)$/,
          use: [ { loader: 'file-loader', options: { name: 'fonts/[name].[ext]' } } ]
        }
      ]
    },
    plugins: [ 
      new MiniCssExtractPlugin({ 
        filename: 'app.css',
        chunkFilename: 'app.[name].css',
      }),
    ],
  },
  require('./kiss/webpack.config.js')
]