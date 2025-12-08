const path = require('path');

module.exports = {
    entry: './assets/js/gutenberg-panel.js', // Путь к вашему JS-файлу
    output: {
        filename: 'gutenberg-panel.bundle.js', // Имя выходного файла
        path: path.resolve(__dirname, 'dist'), // Путь к папке, куда будет сохранен скомпилированный файл
    },
    module: {
        rules: [
            {
                test: /\.js$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader',
                },
            },
        ],
    },
};
