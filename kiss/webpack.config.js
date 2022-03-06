const webpack               = require('webpack');
const path                  = require('path');
const CopyPlugin            = require('copy-webpack-plugin');
const MiniCssExtractPlugin  = require('mini-css-extract-plugin');

module.exports = {
  externals: {
    jquery:        'jQuery',
  },
  entry:            './kiss/src/kiss.js', 
  output: {
    filename:       'kiss.js',
    chunkFilename:  'kiss.[name].js',
    path:           path.resolve(__dirname, '../public/dist/kiss/'),
    publicPath:     '/dist/kiss/',
    library:        'kiss',
  },
  devtool: 'source-map',
  module: {
    rules: [
      {
        test: /\.js$/,
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
      filename: 'kiss.css',
      chunkFilename: 'kiss.[name].css',
    }),
  ]
};