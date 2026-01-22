Get-ChildItem -Recurse -File | Where-Object { $_.Extension -in '.php','.ps1','.md','.txt','.sh','.bat','.json','.yml','.yaml' } | ForEach-Object {
    $content = Get-Content $_.FullName -Raw -ErrorAction SilentlyContinue
    if ($content) {
        $old1 = '[CHAVE_REMOVIDA_POR_SEGURANCA]'
        $old2 = '[CHAVE_REMOVIDA_POR_SEGURANCA]'
        $new = '[CHAVE_REMOVIDA_POR_SEGURANCA]'
        $modified = $false
        if ($content.Contains($old1)) {
            $content = $content.Replace($old1, $new)
            $modified = $true
        }
        if ($content.Contains($old2)) {
            $content = $content.Replace($old2, $new)
            $modified = $true
        }
        if ($modified) {
            [System.IO.File]::WriteAllText($_.FullName, $content, [System.Text.Encoding]::UTF8)
            git add $_.FullName
        }
    }
}

