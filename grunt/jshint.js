module.exports = {
	options: {
		curly:   true,
		eqeqeq:  true,
		immed:   true,
		latedef: true,
		newcap:  true,
		noarg:   true,
		sub:     true,
		undef:   true,
		boss:    true,
		eqnull:  true,
		browser: true,
		devel:   true,
		globals: {
			jQuery: true,
		}
	},
	all:     [
		'js/src/**/*.js',
		'js/test/**/*.js'
	]
};
