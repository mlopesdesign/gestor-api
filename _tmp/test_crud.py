import urllib.request, json, os

BASE = 'https://tools.mlopesdesign.com.br/wp-json/gestor/v1'

def req(method, path, data=None, token=None):
    headers = {'User-Agent': 'Mavis/1.0'}
    body = None
    if data is not None:
        body = json.dumps(data).encode()
        headers['Content-Type'] = 'application/json'
    if token:
        headers['Authorization'] = 'Bearer ' + token
    r = urllib.request.Request(BASE + path, data=body, headers=headers, method=method)
    try:
        resp = urllib.request.urlopen(r, timeout=10)
        return resp.status, resp.read().decode('utf-8', errors='replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', errors='replace')

# 1. login
print('=== 1. login ===')
s, b = req('POST', '/auth/login', {'email': 'mlopesdesign@gmail.com', 'senha': 'Ml@2026gestor'})
print(f'{s} {b[:300]}')
if s != 200:
    raise SystemExit(1)
token = json.loads(b)['data']['token']
print(f'token len: {len(token)}')

# 2. me
print('=== 2. me ===')
s, b = req('GET', '/auth/me', token=token)
print(f'{s} {b[:300]}')

# 3. listar tarefas (vazio)
print('=== 3. listar tarefas (vazio) ===')
s, b = req('GET', '/tarefas', token=token)
print(f'{s} {b[:200]}')

# 4. criar area
print('=== 4. criar area "Trabalho" ===')
s, b = req('POST', '/areas', {'nome': 'Trabalho', 'cor': '#1E88E5'}, token=token)
print(f'{s} {b[:300]}')
area_id = json.loads(b)['data']['id'] if s in (200, 201) else None

# 5. criar cliente
print('=== 5. criar cliente "Cliente Premium" ===')
s, b = req('POST', '/clientes', {'nome': 'Cliente Premium', 'organizacao': 'Premium S.A.', 'contatos_json': '[]'}, token=token)
print(f'{s} {b[:300]}')
cliente_id = json.loads(b)['data']['id'] if s in (200, 201) else None

# 6. criar projeto
print('=== 6. criar projeto "MVP do Cliente" ===')
s, b = req('POST', '/projetos', {'titulo': 'MVP do Cliente', 'cliente_id': cliente_id, 'area_id': area_id}, token=token)
print(f'{s} {b[:300]}')
projeto_id = json.loads(b)['data']['id'] if s in (200, 201) else None

# 7. criar tarefa
print('=== 7. criar tarefa "Estudar PHP 8" ===')
s, b = req('POST', '/tarefas', {
    'titulo': 'Estudar PHP 8',
    'descricao': 'Revisar strict_types e tipos de retorno',
    'projeto_id': projeto_id,
    'cliente_id': cliente_id,
    'area_id': area_id,
    'prioridade': 'ALTA',
    'etiquetas_json': '["php", "estudo"]'
}, token=token)
print(f'{s} {b[:300]}')
tarefa_id = json.loads(b)['data']['id'] if s in (200, 201) else None

# 8. listar tarefas (1 item)
print('=== 8. listar tarefas (1 item) ===')
s, b = req('GET', '/tarefas', token=token)
print(f'{s} {b[:400]}')

# 9. listar hoje
print('=== 9. listar hoje ===')
s, b = req('GET', '/tarefas/hoje', token=token)
print(f'{s} {b[:400]}')

# 10. concluir tarefa
print('=== 10. concluir tarefa ===')
s, b = req('POST', f'/tarefas/{tarefa_id}/concluir', token=token)
print(f'{s} {b[:200]}')

print()
print('=== RESULTADO ===')
print('token:', token[:30] + '...')
print('area:', area_id)
print('cliente:', cliente_id)
print('projeto:', projeto_id)
print('tarefa:', tarefa_id)
