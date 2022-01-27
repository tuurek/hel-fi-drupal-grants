const { v4: uuidv4 } = require('uuid');

const documentRoutes = (app, fs) => {

    // variables
    const dataPath = './data/v1/documents.json';


    // helper methods
    const readFile = (callback, returnJson = false, filePath = dataPath, encoding = 'utf8') => {
        fs.readFile(filePath, encoding, (err, data) => {
            if (err) {
                throw err;
            }

            callback(returnJson ? JSON.parse(data) : data);
        });
    };

    const writeFile = (fileData, callback, filePath = dataPath, encoding = 'utf8') => {

        fs.writeFile(filePath, fileData, encoding, (err) => {
            if (err) {
                throw err;
            }

            callback();
        });
    };

    // READ
    app.get('/v1/documents', (req, res) => {

        const business_id = req.query.business_id
        const transaction_id = req.query.transaction_id
        const type = req.query.type

        fs.readFile(dataPath, 'utf8', (err, data) => {
            if (err) {
                throw err;
            }

            let dataObject = JSON.parse(data)
            const dataArray = Object.keys(dataObject).map(function(k){return dataObject[k]});

            const retval = dataArray
                .filter(item => {
                    if(item.type === undefined) return false

                    return item.type == type
                })
                .map(item => item)
                .filter(item => {
                    if(item.business_id === undefined) return false
                    return item.business_id === business_id
                })
                .filter(item => {
                    if(item.transaction_id === undefined) return false
                    return item.transaction_id === business_id
                })

            if(retval.length === 0) {
                res.sendStatus(404);
            } else {
                const responseData = {
                    "count": retval.length,
                    "next": null,
                    "previous": null,
                    "results": retval
                }

                res.send(responseData);
            }
        });
    });


    // GET SINGLE
    app.get('/v1/documents/:id', (req, res) => {

        // add the new user
        const documentId = req.params["id"];

        fs.readFile(dataPath, 'utf8', (err, data) => {
            if (err) {
                throw err;
            }

            const dataObject = JSON.parse(data)
            const dataArray = Object.keys(dataObject).map(function(k){return dataObject[k]});

            document = dataArray.filter(item => item.id === documentId)

            if(document.length === 0) {
                res.sendStatus(404);
            } else {
                res.send(JSON.stringify(document));
            }


        });
    });

    // CREATE
    app.post('/v1/documents/', (req, res) => {
        console.log('POST', req.body)

        readFile(data => {
                const newDocumentId = uuidv4()


                // newData = JSON.parse(req.body)

                newData = req.body

                newData.created_at = '2022-01-24T11:44:02.400224+02:00'
                newData.updated_at = '2022-01-24T11:44:02.400224+02:00'
                newData.locked_after = "2023-08-01T03:00:00+03:00"
                newData.tos_record_id = 'eb30af1d9d654ebc98287ca25f231bf6'
                newData.tos_function_id = 'eb30af1d9d654ebc98287ca25f231bf6'
                newData.content = req.body.content.replace('\t','')
                newData.content = newData.content.replace('\n','')
                newData.id = newDocumentId

                // add the new user
                data[newDocumentId] = req.body;

                writeFile(JSON.stringify(data, null, 2), () => {
                    res.status(200).send(data[newDocumentId]);
                });
            },
            true);
    });



    // CREATE
    app.patch('/v1/documents/:id', (req, res) => {

        console.log('PATCH',req.body)
        // add the new user
        const documentId = req.params["id"];

        readFile(data => {

                const document = data[documentId]

                const patchContent = req.body

                // let content

                // if(typeof document.content === 'string') {
                //     content = JSON.parse(document.content)
                // } else {
                //     content = document.content
                // }


                // for (const [key, value] of Object.entries(req.body.content)) {
                //     content[key] = value
                //   }

                // console.log(content,dataArray)

                document.content = JSON.stringify(patchContent.content)
                data[documentId] = document

                writeFile(JSON.stringify(data, null, 2), () => {
                    res.status(200).send(data[documentId]);
                });
            },
            true);

        res.status(200).send({});
    });

    // UPDATE
    app.put('/v1/documents/:id', (req, res) => {

        // add the new user
        const documentId = req.params["id"];

        readFile(data => {

                data[documentId] = req.body;

                writeFile(JSON.stringify(data, null, 2), () => {
                    res.status(200).send(data[documentId]);
                });
            },
            true);
    });


    // DELETE
    app.delete('/v1/documents/:id', (req, res) => {

        readFile(data => {

                // delete the user
                const userId = req.params["id"];
                delete data[userId];

                writeFile(JSON.stringify(data, null, 2), () => {
                    res.status(200).send(`users id:${userId} removed`);
                });
            },
            true);
    });
};

module.exports = documentRoutes;
