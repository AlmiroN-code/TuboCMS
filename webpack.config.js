const Encore = require('@symfony/webpack-encore');

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')
    
    // Entry points
    .addEntry('app', './assets/app.js')
    
    // Enable Stimulus bridge
    .enableStimulusBridge('./assets/controllers.json')
    
    // Split chunks for better caching
    .splitEntryChunks()
    .enableSingleRuntimeChunk()
    
    // Clean output before build
    .cleanupOutputBeforeBuild()
    
    // Enable build notifications
    .enableBuildNotifications()
    
    // Source maps
    .enableSourceMaps(!Encore.isProduction())
    
    // Versioning for cache busting
    .enableVersioning(Encore.isProduction())
    
    // Babel configuration
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = '3.39';
    })
    
    // PostCSS for Tailwind
    .enablePostCssLoader((options) => {
        options.postcssOptions = {
            plugins: [
                require('tailwindcss'),
                require('autoprefixer'),
            ]
        };
    })
;

module.exports = Encore.getWebpackConfig();
