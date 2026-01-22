Get-ChildItem -Recurse -File | Where-Object { $_.Extension -in '.php','.ps1','.md','.txt','.sh','.bat' } | ForEach-Object {
    $content = Get-Content $_.FullName -Raw -ErrorAction SilentlyContinue
    if ($content) {
        $old = '[CHAVE_REMOVIDA_POR_SEGURANCA]'
        $new = '[CHAVE_REMOVIDA_POR_SEGURANCA]'
        if ($content.Contains($old)) {
            $content = $content.Replace($old, $new)
            [System.IO.File]::WriteAllText($_.FullName, $content, [System.Text.Encoding]::UTF8)
            git add $_.FullName
        }
    }
}
