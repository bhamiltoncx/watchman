var fs = require('fs')
var glob = require('glob');
var mkdirp = require('mkdirp');
var optimist = require('optimist');
var path = require('path');
var argv = optimist.argv;

function splitHeader(content) {
  var lines = content.split('\n');
  for (var i = 1; i < lines.length - 1; ++i) {
    if (lines[i] === '---') {
      break;
    }
  }
  return {
    header: lines.slice(1, i + 1).join('\n'),
    content: lines.slice(i + 1).join('\n')
  };
}

function backtickify(str) {
  return '`' + str.replace(/\\/g, '\\\\').replace(/`/g, '\\`') + '`';
}

function execute() {
  var MD_DIR = '../docs/';

  glob('src/watchman/docs/*.*', function(er, files) {
    files.forEach(function(file) {
      fs.unlinkSync(file);
    });
  });

  var metadatas = {
    files: [],
  };

  glob(MD_DIR + '**/*.*', function (er, files) {
    files.forEach(function(file) {
      var extension = path.extname(file);
      if (extension === '.md' || extension === '.markdown') {
        var content = fs.readFileSync(file, {encoding: 'utf8'});
        var metadata = {};

        // Extract markdown metadata header
        var both = splitHeader(content);
        var lines = both.header.split('\n');
        for (var i = 0; i < lines.length - 1; ++i) {
          var keyvalue = lines[i].split(':');
          var key = keyvalue[0].trim();
          var value = keyvalue[1].trim();
          // Handle the case where you have "Community #10"
          try { value = JSON.parse(value); } catch(e) { }
          metadata[key] = value;
        }
        metadatas.files.push(metadata);

        // Create a dummy .js version that just calls the associated layout
        var layout = metadata.layout[0].toUpperCase() + metadata.layout.substr(1) + 'Layout';

        var content = (
          '/**\n' +
          ' * @generated\n' +
          ' * @jsx React.DOM\n' +
          ' */\n' +
          'var React = require("React");\n' +
          'var layout = require("' + layout + '");\n' +
          'module.exports = React.createClass({\n' +
          '  render: function() {\n' +
          '    return layout({metadata: ' + JSON.stringify(metadata) + '}, ' + backtickify(both.content) + ');\n' +
          '  }\n' +
          '});\n'
        );

        var targetFile = 'src/watchman/' + metadata.permalink.replace(/\.html$/, '.js');
        mkdirp.sync(targetFile.replace(new RegExp('/[^/]*$'), ''));
        fs.writeFileSync(targetFile, content);
      }

      if (extension === '.json') {
        var content = fs.readFileSync(file, {encoding: 'utf8'});
        metadatas[path.basename(file, '.json')] = JSON.parse(content);
      }
    });

    fs.writeFileSync(
      'core/metadata.js',
      '/**\n' +
      ' * @generated\n' +
      ' * @providesModule Metadata\n' +
      ' */\n' +
      'module.exports = ' + JSON.stringify(metadatas, null, 2) + ';'
    );
  });
}

if (argv.convert) {
  console.log('convert!')
  execute();
}

module.exports = execute;
