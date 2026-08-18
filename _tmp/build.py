import os, zipfile, hashlib
src = r'E:\Projetos\LOPES FOCUS\wp-api\gestor-api'
out = r'E:\Projetos\LOPES FOCUS\wp-api\gestor-api-0.1.3.zip'
if os.path.exists(out):
    os.remove(out)
files = []
for root, dirs, fnames in os.walk(src):
    dirs[:] = [d for d in dirs if not d.startswith('.git')]
    for f in fnames:
        if f.startswith('.') or f.endswith('~'):
            continue
        full = os.path.join(root, f)
        rel = os.path.relpath(full, os.path.dirname(src)).replace(os.sep, '/')
        files.append((full, rel))
print(f'{len(files)} arquivos')
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED, compresslevel=6) as z:
    for full, zp in files:
        z.write(full, zp)
h = hashlib.sha256()
with open(out, 'rb') as fp:
    for chunk in iter(lambda: fp.read(8192), b''):
        h.update(chunk)
print(f'Size: {os.path.getsize(out)} bytes')
print(f'SHA-256: {h.hexdigest()}')
with zipfile.ZipFile(out) as z:
    names = z.namelist()
    roots = set(n.split('/')[0] for n in names)
    single_root = (roots == {'gestor-api'})
    print(f'Raiz unica gestor-api: {single_root}')
    print(f"gestor-api/gestor-api.php: {'gestor-api/gestor-api.php' in names}")
    print(f"gestor-api/includes/db/class-schema.php: {'gestor-api/includes/db/class-schema.php' in names}")
