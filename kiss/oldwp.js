const path = require('path');
const CopyPlugin = require('copy-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');


const KissConfiguration = {
  entry: {
    core:         './kiss/src/kiss/kiss.js',
    fontawesome:  './kiss/src/font-awesome/FontAwesome.js',
  },
  output: {
    filename: 'kiss.[name].js',
    chunkFilename: 'kiss.[name].js',
    path: path.resolve(__dirname, '../public/dist'),
    publicPath: '/dist/',
    library: 'kiss',
  },
  module: {
    rules: [
      {
        test: /\.s[ac]ss$/i,
        use: [
          MiniCssExtractPlugin.loader,
          { loader: 'css-loader' },
          { loader: 'sass-loader', options: { sourceMap: true } },
        ]
      },
      {
        test: /view.*\.s?[ac]ss$/i,
        use: [ 'style-loader', 'css-loader', 'sass-loader' ],
      },
      {         
        test: /\.(woff|woff2|eot|ttf|otf|svg)$/,
        use: [ { loader: 'file-loader',  options: { name: 'fonts/[name].[ext]' } } ]
      }
    ]
  },
  plugins: [ new MiniCssExtractPlugin({ filename: 'kiss.[name].css' }) ],
  externals: {
    kiss: 'kiss',
    jquery: 'jQuery',
  }
};

const FontAwesomeConfiguration = {
  entry: './kiss/src/font-awesome/FontAwesome.js',
  output: {
    filename:       'fa.js',
    chunkFilename:  'fa.[name].js',
    path:           path.resolve(__dirname, '../public/dist'),
    publicPath:     '/dist/',
  },
  module: {
    rules: [ 
      {
        test: /\.s[ac]ss$/i,
        use: [
          { loader: 'css-loader' },
          { loader: 'sass-loader', options: { sourceMap: true } },
        ]
      },   
      {         
        test: /\.(woff|woff2|eot|ttf|otf|svg)$/,
        use: [ { loader: 'file-loader',  options: { name: 'fonts/[name].[ext]' } } ]
      }
    ]
  }
}

module.exports = [
  KissConfiguration,
];