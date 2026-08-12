function JSON_API(json = {}, id = 7, method = "GET", mode = "user") {
    console.log(mode, json);
    let param = [];
    if (method == "POST") {
        var data = new FormData();
        data.append("mode", mode);
        data.append("json", JSON.stringify(json, null, 2));
        // payload.body=data;
        // // console.log(payload)
        // let headers = {"Access-Control-Allow-Origin" : "*", 
        //     'content-type': 'application/json'}
        param = [{ method, body: data }]
    }
    let problem_id = id || 7
    let query = ""
    if (mode == "admin") {
        query = "&mode=admin"
    }

    // Which endpoint to talk to is decided by the page, not by sniffing the
    // hostname: each version sets window.GG_API (see includes/game_view.php).
    // public/ points at its own read-only endpoint; brightspace/ and admin/ use
    // the session-aware one, which is also the default for the course editor.
    // Paths are absolute because the pages live in subdirectories.
    let api = (typeof window !== "undefined" && window.GG_API) || "/problem_set.php"
    let URL = `${api}?id=${problem_id}${query}`
    if (window.location.href.includes("github.io")) {
        // Static hosting (GitHub Pages): no PHP, read the JSON directly.
        URL = `/problem_sets/problem_${problem_id}.json${query}`
    }
    param.unshift(URL);
    // console.log(param)
    return fetch(...param)
        .then(res => {
            if (res.status >= 200 && res.status < 300) {
                // console.log(res);
                return res.json()
            } else {
                throw new Error();
            }
            
        })
        .then((data) => { return data })
        
}

function parseQuery(queryString) {
    var query = {};
    var pairs = (queryString[0] === '?' ? queryString.substr(1) : queryString).split('&');
    for (var i = 0; i < pairs.length; i++) {
        var pair = pairs[i].split('=');
        query[decodeURIComponent(pair[0])] = decodeURIComponent(pair[1] || '').replaceAll("+", " ");
    }
    //console.log(query)
    return query;
}