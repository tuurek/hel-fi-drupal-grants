// import other routes
const userRoutes = require('./users');
const documentRoutes = require('./v1/documents')

const appRouter = (app, fs) => {

    // default route
    app.get('/', (req, res) => {
        res.send('welcome to the development api-server');
    });

    // // other routes
    userRoutes(app, fs);
    // // other routes
    documentRoutes(app, fs);

};

module.exports = appRouter;