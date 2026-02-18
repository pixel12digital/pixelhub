// Debug do endpoint
console.log('=== DEBUG ENDPOINT ===');

// Verifica se a URL base está correta
console.log('URL base:', window.location.origin);
console.log('URL completa:', window.location.origin + '/opportunities/followup-details?id=2');

// Tenta diferentes URLs
const urls = [
    '/opportunities/followup-details?id=2',
    '<?= pixelhub_url('/opportunities/followup-details') ?>?id=2',
    '/painel.pixel12digital/opportunities/followup-details?id=2'
];

urls.forEach((url, i) => {
    console.log('Testando URL ' + i + ':', url);
    fetch(url)
        .then(res => {
            console.log('Status:', res.status);
            console.log('URL ' + i + ' OK:', res.ok);
            return res.text();
        })
        .then(text => {
            console.log('Resposta URL ' + i + ':', text.substring(0, 100) + '...');
        })
        .catch(err => {
            console.log('Erro URL ' + i + ':', err.message);
        });
});
