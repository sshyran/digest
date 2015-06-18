module.exports = {
	options: {
		stripBanners: true,
		banner      : '/*! <%= package.title %> - v<%= package.version %>\n' +
		' * <%= package.homepage %>\n' +
		' * Copyright (c) <%= grunt.template.today("yyyy") %>;' +
		' * Licensed GPLv2+' +
		' */\n',
		separator   : ';\n'
	},
	dist   : {
		src : [
			'js/src/wp-digest.js'
		],
		dest: 'js/wp-digest.js'
	}
}
