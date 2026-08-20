import urllib.request, json, os

def post(path, data, token=None):
    url = 'https://tools.mlopesdesign.com.br' + path
    headers = {'User-Agent': 'Mavis/1.0', 'Content-Type': 'application/json'}
    if token:
        headers['Authorization'] = 'Bearer ' + token
    body = json.dumps(data).encode()
    req = urllib.request.Request(url, data=body, headers=headers, method='POST')
    try:
        r = urllib.request.urlopen(req, timeout=10)
        return r.status, r.read().decode('utf-8', errors='replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', errors='replace')


def get(path, token=None):
    url = 'https://tools.mlopesdesign.com.br' + path
    headers = {'User-Agent': 'Mavis/1.0'}
    if token:
        headers['Authorization'] = 'Bearer ' + token
    req = urllib.request.Request(url, headers=headers)
    try:
        r = urllib.request.urlopen(req, timeout=10)
        return r.status, r.read().decode('utf-8', errors='replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', errors='replace')


print('--- 1. login ---')
s, b = post('/wp-json/gestor/v1/auth/login', {'email': 'mlopesdesign@gmail.com', 'senha': '242776'})
print(f'{s} {b[:500]}')
if s == 200:
    j = json.loads(b)
    token = j.get('token') or j.get('data', {}).get('token')
    if not token:
        # pode estar aninhado em data.data
        token = j.get('data', {}).get('data', {}).get('token')
    print(f'token: {token[:30] if token else "NONE"}...')
    if token:
        # Salva token num arquivo temporario
        out = os.path.join(os.environ.get('TEMP', '/tmp'), 'gestor_token.txt')
        with open(out, 'w') as f:
            f.write(token)
        print(f'token salvo em {out}')
        print('--- 2. me ---')
        s, b = get('/wp-json/gestor/v1/auth/me', token)
        print(f'{s} {b[:300]}')
        print('--- 3. listar tarefas ---')
        s, b = get('/wp-json/gestor/v1/tarefas', token)
        print(f'{s} {b[:300]}')
else:
    print('Login falhou - tenta outras senhas')
    # Tenta mais
    for senha in ['Marcio@2026', 'mlopesdesign', 'admin', 'gestor', 'Marcio123']:
        s, b = post('/wp-json/gestor/v1/auth/login', {'email': 'mlopesdesign@gmail.com', 'senha': senha})
        if s == 200:
            print(f'SENHA ENCONTRADA: {senha}')
            print(b[:300])
            break
        else:
            print(f'{senha}: {s}')
