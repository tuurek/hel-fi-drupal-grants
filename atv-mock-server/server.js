const express = require('express');
const bodyParser = require('body-parser');
const app = express();
const fs = require('fs');

/** Require multer */
var multer = require('multer');
var upload = multer();


app.use(express.json());
app.use(express.urlencoded());

// for parsing multipart/form-data
app.use(upload.array());



const routes = require('./routes/routes.js')(app, fs);

const server = app.listen(3001, () => {
    console.log('listening on port %s...', server.address().port);
});