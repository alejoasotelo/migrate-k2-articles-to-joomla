async function migrar(userId, baseUrl, limit) {
    var pageErrors = [];

    limit = limit || 20;

    var response = await fetch(baseUrl + '/cli/k2tojoomla/web.php?userid='+userId+'&type=categories&ajax=1', { method: 'POST' });

    response = await response.json();

    console.log('Categories response', response);

    var pages = Math.ceil(response.countArticles / limit);

    console.log('Paginas', pages);

    for (var page = 0; page < pages; page++) {
        //var page = 0;
        //console.log('http://127.0.0.1/k2toarticles/cli/k2tojoomla/web.php?userid=143&type=articles&ajax=1&page=' + page + '&limit=' + limit);

        try {
            var r = await fetch(baseUrl + '/cli/k2tojoomla/web.php?userid='+userId+'&type=articles&ajax=1&page=' + page + '&limit=' + limit, {
                method: 'POST',
                body: JSON.stringify({
                    mapCategories: response.mapCategories
                })
            });

            var json = await r.json();
            console.log('Articulos migrados. Página ' + page + ', limit: ' + limit, json);
        } catch(error) {
            pageErrors.push(page);

            console.error('Error en la Página: ' + page + ', limit: ' + limit, error);

        }
    }

    return pageErrors;
}

let result = migrar(143, 'http://127.0.0.1/k2toarticles', 30);

console.log(result);