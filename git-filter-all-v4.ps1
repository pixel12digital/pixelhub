Get-ChildItem -Recurse -File | Where-Object { $_.Extension -in '.php','.ps1','.md','.txt','.sh','.bat','.json','.yml','.yaml','.log','.js','.ts','.html','.css' } | ForEach-Object {
    $content = Get-Content $_.FullName -Raw -ErrorAction SilentlyContinue
    if ($content) {
        # PadrÃµes mais robustos que capturam a chave mesmo quebrada em mÃºltiplas linhas
        $patterns = @(
            # Chave completa com $ no inÃ­cio
            '\[CHAVE_REMOVIDA_POR_SEGURANCA]',
            # Chave completa sem $ no inÃ­cio
            '[CHAVE_REMOVIDA_POR_SEGURANCA]',
            # Chave quebrada em mÃºltiplas linhas (com espaÃ§os/quebras)
            '\[CHAVE_REMOVIDA_POR_SEGURANCA]',
            '[CHAVE_REMOVIDA_POR_SEGURANCA]',
            # Chave em comandos git (com backticks e escapes)
            '\\`\[CHAVE_REMOVIDA_POR_SEGURANCA]',
            '\\\[CHAVE_REMOVIDA_POR_SEGURANCA]'
        )
        $modified = $false
        foreach ($pattern in $patterns) {
            if ($content -match $pattern) {
                $content = $content -replace $pattern, '[CHAVE_REMOVIDA_POR_SEGURANCA]'
                $modified = $true
            }
        }
        if ($modified) {
            [System.IO.File]::WriteAllText($_.FullName, $content, [System.Text.Encoding]::UTF8)
            git add $_.FullName
        }
    }
}

