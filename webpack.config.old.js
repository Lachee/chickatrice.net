const webpack               = require('webpack');
const path                  = require('path');
const CopyPlugin            = require('copy-webpack-plugin');
const MiniCssExtractPlugin  = require('mini-css-extract-plugin');
const DashboardPlugin       = require("webpack-dashboard/plugin");

const Externals = {    
  kiss: 'kiss',
  jquery: 'jQuery',
}

const JSRule = {
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
};

const AppConfiguration = {
    entry: './src/app/app.js',
    output: {
        filename: 'app.js',
        chunkFilename: 'app.[name].js',
        path: path.resolve(__dirname, './public/dist'),
        publicPath: '/dist/',
        library: 'app',
    },
    module: {
      rules: [
        JSRule,
        {
          test: /\.s?[ac]ss$/i,
          exclude: /view.*/,
          use: [
            MiniCssExtractPlugin.loader,
            { loader: 'css-loader' },
            { loader: 'sass-loader', options: { sourceMap: true } },
          ]
        },
        {
          test: /view\.*\.s?[ac]ss$/i,
          use: [ 'style-loader', 'css-loader', 'sass-loader' ],
        },
        {         
          test: /\.(woff|woff2|eot|ttf|otf|svg)$/,
          use: [ {
            loader: 'file-loader',
            options: { name: 'fonts/[name].[ext]' }
          }]
        }
      ]
    },
    plugins: [ 
       new MiniCssExtractPlugin({ 
        filename: 'app.css',
        chunkFilename: 'app.[name].css',
      }),
      new DashboardPlugin()
   ],
    externals: Externals
};

module.exports = [
    AppConfiguration
].concat(require('./kiss/webpack.config'));