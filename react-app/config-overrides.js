const { override, addBabelPlugin, addBabelPreset } = require('customize-cra');
module.exports = override(
    addBabelPlugin('styled-jsx/babel')
);

// module.exports = function override(config, env) {
//   // console.log(config.output);
//   // asdf();
//   config.output = {
//       ...config.output, // copy all settings
//       filename: "static/js/[name].js",
//       chunkFilename: "static/js/[name].chunk.js",
//       assetModuleFilename: 'static/media/[name].[ext]',
//   };
//   return config;
// };