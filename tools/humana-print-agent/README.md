# Humana Print Agent

Agente Windows de impressão térmica para o Totem humanaClinica.

## Onde roda

| Ambiente | O quê |
|----------|--------|
| Clínica | Apenas o **binário self-contained** do Humana Print Agent na **CPU Windows** do Totem |
| Desenvolvimento | .NET 8 SDK (compilar / testar / publicar) |

**Não** instalar no tablet Android: SDK, Runtime, agente ou drivers Windows.

## Development (Mock + Local)

```powershell
cd tools/humana-print-agent/src/HumanaPrintAgent
dotnet run
```

- Escuta: `http://127.0.0.1:17321` (`ListenMode=Local` por padrão)
- Provider: `Mock` (grava em `%ProgramData%\HumanaPrintAgent\mock-jobs`)
- `Print:PairingSecret` = segredo gerado no admin do Totem

## Self-contained (clínica)

```powershell
dotnet publish src/HumanaPrintAgent/HumanaPrintAgent.csproj `
  -c Release `
  -r win-x64 `
  --self-contained true `
  -p:PublishSingleFile=true `
  -o artifacts/win-x64
```

Na clínica: copiar `artifacts/win-x64` e executar. **Não** precisa de .NET SDK nem Runtime separado (self-contained inclui o runtime).

## Windows Service (futuro / instalador)

```powershell
sc.exe create HumanaPrintAgent binPath= "C:\Path\HumanaPrintAgent.exe" start= auto
sc.exe start HumanaPrintAgent
```

Estrutura pronta para um futuro `HumanaPrintAgentSetup.exe` / MSI (não implementado nesta etapa).

## Configuração (`appsettings.json`)

```json
{
  "Print": {
    "Mode": "Mock",
    "ListenMode": "Local",
    "Port": 17321,
    "BindHost": "",
    "PairingSecret": "...",
    "AllowedOrigins": [],
    "MaxRequestBytes": 65536,
    "RateLimitPerMinute": 120
  }
}
```

| Chave | Significado |
|-------|-------------|
| `Mode` | Provider: `Mock` \| `Spooler` |
| `ListenMode` | Rede: `Local` \| `Lan` (**default Local**; Lan nunca automático) |
| `Port` | Porta TCP |
| `BindHost` | Obrigatório em Lan (ex. `192.168.1.50` ou `0.0.0.0` se consciente) |
| `PairingSecret` | Segredo compartilhado com Laravel (≥ 32 chars) |
| `AllowedOrigins` | Allowlist CORS (recomendado em Lan). **CORS não é autenticação.** |

### Local

Bind forçado: `http://127.0.0.1:{Port}`.

### Lan

1. Técnico define `ListenMode=Lan` e `BindHost` no agente.
2. No admin do Totem: modo LAN + IP da CPU Windows.
3. Pareamento (segredo) + seleção de impressora + teste.
4. Autenticação real: grants HMAC de curta duração (mesmo modelo do Local).

## Endpoints

| Método | Path | Auth |
|--------|------|------|
| GET | `/health` | livre (sem secrets) |
| POST | `/printers` | grant assinado |
| POST | `/print/ticket` | grant assinado |
| POST | `/print/test` | grant assinado |

Sem endpoint RAW genérico, sem shell, sem upload executável.

## Segurança

- Segredo longo: Laravel (criptografado) + config do agente. **Nunca** no HTML/JS do Totem.
- Navegador recebe só grant + signature + `agentUrl`.
- Dispositivo sem pareamento / assinatura inválida → 401.
- Rate limit por IP + limite de body.
- Lan não é default; bind explícito.

### Modelo de ameaça / limitações

- Qualquer host na LAN que alcance o bind pode *tentar* falar com o agente; sem HMAC válido não imprime.
- Grants efêmeros no browser podem ser interceptados na LAN se o tráfego for HTTP claro — mitigações futuras: HTTPS no agente / rede segmentada.
- Mixed content: Totem HTTPS + agente HTTP pode ser bloqueado pelo browser — preferir HTTP na LAN da clínica ou HTTPS no agente (futuro).
- CORS não autentica; HMAC autentica.

## Pareamento (simples)

1. Instalar agente na CPU Windows.
2. Admin → Totem → Parear agente → copiar segredo para `Print:PairingSecret`.
3. Atualizar impressoras → selecionar → Testar → salvar.
4. Android: mesmo fluxo com modo LAN + IP da CPU.
