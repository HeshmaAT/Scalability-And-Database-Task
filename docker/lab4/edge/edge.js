function expiry(r) {
    const uri = r.variables.lab4_original_uri || '';
    const query = uri.indexOf('?') >= 0 ? uri.slice(uri.indexOf('?')+1) : '';
    const entries = query.split('&').filter(pair => pair.split('=')[0] === 'expires');
    if (entries.length !== 1) { r.return(403); return; }
    const expires = entries[0].split('=')[1] || '';
    if (!/^[0-9]+$/.test(expires) || Number(expires) < Date.now()/1000) {
        r.return(403); return;
    }
    r.return(204);
}

function age(r) {
    const value = Number(r.headersOut['X-Lab4-Origin-Time']);
    if (r.status === 200 && Number.isFinite(value) && value > 0) {
        r.headersOut['Age'] = String(Math.max(0, Math.floor(Date.now()/1000 - value)));
    }
    delete r.headersOut['X-Lab4-Origin-Time'];
}

export default {expiry, age};
