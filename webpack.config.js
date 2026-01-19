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
    
    // Оптимизация разделения кода
    .splitEntryChunks()
    .enableSingleRuntimeChunk()
    
    // Очистка перед сборкой
    .cleanupOutputBeforeBuild()
    
    // Уведомления о сборке только в dev
    .enableBuildNotifications(!Encore.isProduction())
    
    // Source maps только в dev
    .enableSourceMaps(!Encore.isProduction())
    
    // Версионирование для cache busting в production
    .enableVersioning(Encore.isProduction())
    
    // Оптимизация Babel
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = '3.39';
        // Исключаем полифиллы для современных браузеров в production
        if (Encore.isProduction()) {
            config.targets = {
                browsers: ['> 1%', 'last 2 versions', 'not ie <= 11']
            };
        }
    })
    
    // PostCSS для Tailwind с оптимизацией
    .enablePostCssLoader((options) => {
        options.postcssOptions = {
            plugins: [
                require('tailwindcss'),
                require('autoprefixer'),
                // Минификация CSS в production
                ...(Encore.isProduction() ? [require('cssnano')({
                    preset: ['default', {
                        discardComments: { removeAll: true },
                        normalizeWhitespace: true,
                        minifySelectors: true
                    }]
                })] : [])
            ]
        };
    })
    
    // Оптимизация изображений
    .copyFiles({
        from: './assets/images',
        to: 'images/[path][name].[hash:8].[ext]',
        pattern: /\.(png|jpg|jpeg|gif|ico|svg|webp)$/
    })
;

// Дополнительные оптимизации для production
if (Encore.isProduction()) {
    Encore
        // Минификация JS
        .enableIntegrityHashes(true)
        
        // Оптимизация размера бандла
        .configureOptimization((optimization) => {
            optimization.minimize = true;
            optimization.sideEffects = false;
            
            // Разделение vendor библиотек
            optimization.splitChunks = {
                cacheGroups: {
                    vendor: {
                        test: /[\\/]node_modules[\\/]/,
                        name: 'vendors',
                        chunks: 'all',
                        priority: 10
                    },
                    common: {
                        name: 'common',
                        minChunks: 2,
                        chunks: 'all',
                        priority: 5,
                        reuseExistingChunk: true
                    }
                }
            };
        })
        
        // Анализ размера бандла (опционально)
        // .addPlugin(new (require('webpack-bundle-analyzer').BundleAnalyzerPlugin)())
    ;
}

// Настройки для dev сервера
if (!Encore.isProduction()) {
    Encore
        .configureDevServerOptions(options => {
            options.hot = true;
            options.liveReload = true;
            options.watchFiles = {
                paths: ['templates/**/*.twig'],
                options: {
                    usePolling: false,
                }
            };
        });
}

module.exports = Encore.getWebpackConfig();
