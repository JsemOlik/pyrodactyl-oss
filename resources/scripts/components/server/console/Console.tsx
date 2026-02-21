import {
    ArrowDownToLine,
    ChevronLeft,
    ChevronRight,
    Copy,
    Magnifier,
    Terminal as TerminalIcon,
} from '@gravity-ui/icons';
import { FitAddon } from '@xterm/addon-fit';
import { SearchAddon } from '@xterm/addon-search';
import { WebLinksAddon } from '@xterm/addon-web-links';
import { ITerminalOptions, Terminal } from '@xterm/xterm';
import '@xterm/xterm/css/xterm.css';
import clsx from 'clsx';
import debounce from 'debounce';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';

import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import { SocketEvent, SocketRequest } from '@/components/server/events';

import { ServerContext } from '@/state/server';

import useEventListener from '@/plugins/useEventListener';
import { usePermissions } from '@/plugins/usePermissions';
import { usePersistedState } from '@/plugins/usePersistedState';

import styles from './style.module.css';

const theme = {
    // background: 'rgba(0, 0, 0, 0)',
    background: '#131313',
    cursor: 'transparent',
    black: '#000000',
    red: '#E54B4B',
    green: '#9ECE58',
    yellow: '#FAED70',
    blue: '#396FE2',
    magenta: '#BB80B3',
    cyan: '#2DDAFD',
    white: '#d0d0d0',
    brightBlack: 'rgba(255, 255, 255, 0.2)',
    brightRed: '#FF5370',
    brightGreen: '#C3E88D',
    brightYellow: '#FFCB6B',
    brightBlue: '#82AAFF',
    brightMagenta: '#C792EA',
    brightCyan: '#89DDFF',
    brightWhite: '#ffffff',
    selection: '#FAF089',
};

const terminalProps: ITerminalOptions = {
    disableStdin: true,
    cursorStyle: 'underline',
    allowTransparency: true,
    fontSize: window.innerWidth < 640 ? 11 : 12,
    lineHeight: 1.5,
    fontFamily: 'ui-monospace, SFMono-Regular, "SF Mono", Consolas, "Liberation Mono", Menlo, monospace',
    theme: { ...theme, background: 'transparent' },
};

const Console = () => {
    const TERMINAL_PRELUDE = '\u001b[1m\u001b[33mcontainer@pyrodactyl~ \u001b[0m';
    const ref = useRef<HTMLDivElement>(null);
    const terminal = useMemo(
        () =>
            new Terminal({
                ...terminalProps,
                rows: window.innerWidth < 640 ? 20 : 25,
            }),
        [],
    );
    const fitAddonRef = useRef<FitAddon | null>(null);
    const searchAddonRef = useRef<SearchAddon | null>(null);
    const webLinksAddonRef = useRef<WebLinksAddon | null>(null);
    const { connected, instance } = ServerContext.useStoreState((state) => state.socket);
    const [canSendCommands] = usePermissions(['control.console']);
    const [searchQuery, setSearchQuery] = useState('');
    const [matchCount, setMatchCount] = useState(0);
    const lastSearchTerm = useRef<string>('');
    const serverId = ServerContext.useStoreState((state) => state.server.data!.id);
    const isTransferring = ServerContext.useStoreState((state) => state.server.data!.isTransferring);
    const [history, setHistory] = usePersistedState<string[]>(`${serverId}:command_history`, []);
    const [historyIndex, setHistoryIndex] = useState(-1);
    const [fontSize, setFontSize] = useState(window.innerWidth < 640 ? 11 : 12);

    const stripedBackgroundStyle = useMemo(() => {
        const stripeHeight = fontSize * 1.5;
        return {
            backgroundImage: `linear-gradient(to bottom, #131313 0px, #131313 ${stripeHeight}px, #1b1b1b ${stripeHeight}px, #1b1b1b ${stripeHeight * 2}px)`,
            backgroundSize: `100% ${stripeHeight * 2}px`,
        };
    }, [fontSize]);

    const handleConsoleOutput = (line: string, prelude = false) =>
        terminal.writeln((prelude ? TERMINAL_PRELUDE : '') + line.replace(/(?:\r\n|\r|\n)$/im, '') + '\u001b[0m');

    const handleTransferStatus = (status: string) => {
        switch (status) {
            // Sent by either the source or target node if a failure occurs.
            case 'failure':
                terminal.writeln(TERMINAL_PRELUDE + 'Transfer has failed.\u001b[0m');
                return;
        }
    };

    const handleDaemonErrorOutput = (line: string) =>
        terminal.writeln(
            TERMINAL_PRELUDE + '\u001b[1m\u001b[41m' + line.replace(/(?:\r\n|\r|\n)$/im, '') + '\u001b[0m',
        );

    const handlePowerChangeEvent = (state: string) =>
        terminal.writeln(TERMINAL_PRELUDE + 'Server marked as ' + state + '...\u001b[0m');

    const handleCommandKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'ArrowUp') {
            const newIndex = Math.min(historyIndex + 1, history!.length - 1);

            setHistoryIndex(newIndex);
            e.currentTarget.value = history![newIndex] || '';

            // By default up arrow will also bring the cursor to the start of the line,
            // so we'll preventDefault to keep it at the end.
            e.preventDefault();
        }

        if (e.key === 'ArrowDown') {
            const newIndex = Math.max(historyIndex - 1, -1);

            setHistoryIndex(newIndex);
            e.currentTarget.value = history![newIndex] || '';
        }

        const command = e.currentTarget.value;
        if (e.key === 'Enter' && command.length > 0) {
            setHistory((prevHistory) => [command, ...prevHistory!].slice(0, 32));
            setHistoryIndex(-1);

            if (instance) instance.send('send command', command);
            e.currentTarget.value = '';
        }
    };

    useEffect(() => {
        if (connected && ref.current && !terminal.element) {
            // Lazily create and attach addons once per terminal instance.
            if (!fitAddonRef.current) {
                fitAddonRef.current = new FitAddon();
                terminal.loadAddon(fitAddonRef.current);
            }

            if (!searchAddonRef.current) {
                searchAddonRef.current = new SearchAddon();
                terminal.loadAddon(searchAddonRef.current);
            }

            if (!webLinksAddonRef.current) {
                webLinksAddonRef.current = new WebLinksAddon();
                terminal.loadAddon(webLinksAddonRef.current);
            }

            terminal.open(ref.current);
            fitAddonRef.current.fit();

            // Add support for capturing keys
            terminal.attachCustomKeyEventHandler((e: KeyboardEvent) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 'c') {
                    document.execCommand('copy');
                    return false;
                }
                return true;
            });
        }
    }, [terminal, connected]);

    useEventListener(
        'resize',
        debounce(() => {
            if (terminal.element && fitAddonRef.current) {
                // Update font size based on window width
                const newFontSize = window.innerWidth < 640 ? 11 : 12;
                terminal.options.fontSize = newFontSize;
                setFontSize(newFontSize);
                fitAddonRef.current.fit();
            }
        }, 100),
    );

    const getConsoleText = () => {
        const buffer = terminal.buffer.active;
        const lines: string[] = [];

        for (let y = 0; y < buffer.length; y++) {
            const line = buffer.getLine(y);
            if (!line) continue;
            lines.push(line.translateToString());
        }

        return lines.join('\n');
    };

    const downloadConsole = () => {
        try {
            const content = getConsoleText();
            const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });

            const now = new Date();
            const pad = (n: number) => n.toString().padStart(2, '0');
            const filename = `Console-${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}_${pad(
                now.getHours(),
            )}-${pad(now.getMinutes())}-${pad(now.getSeconds())}.txt`;

            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);

            toast.success('Console downloaded as text file.');
        } catch (e) {
            // Silently fail if buffer access is not available for some reason.
        }
    };

    const copyConsole = async () => {
        try {
            const content = getConsoleText();
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(content);
            } else {
                const textarea = document.createElement('textarea');
                textarea.value = content;
                textarea.style.position = 'fixed';
                textarea.style.left = '-9999px';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            }

            toast.success('Console copied to clipboard.');
        } catch (e) {
            // Silently ignore copy failures.
        }
    };

    const handleSearch = (value: string) => {
        const term = value.trim();
        const addon = searchAddonRef.current;

        if (!addon) {
            return;
        }

        if (!term) {
            lastSearchTerm.current = '';
            setMatchCount(0);
            // Clear existing search highlights if supported by this SearchAddon version.
            // @ts-expect-error - clearDecorations may not exist on older versions.
            if (typeof (addon as any).clearDecorations === 'function') {
                (addon as any).clearDecorations();
            }
            return;
        }

        lastSearchTerm.current = term;

        // Compute match count across the current buffer.
        try {
            const buffer = terminal.buffer.active;
            const lowerTerm = term.toLowerCase();
            let count = 0;

            for (let y = 0; y < buffer.length; y++) {
                const line = buffer.getLine(y);
                if (!line) continue;
                const text = line.translateToString().toLowerCase();
                if (!text) continue;

                let idx = text.indexOf(lowerTerm);
                while (idx !== -1) {
                    count += 1;
                    idx = text.indexOf(lowerTerm, idx + lowerTerm.length || 1);
                }
            }

            setMatchCount(count);
        } catch (e) {
            // If buffer iteration fails for any reason, just skip the count update.
        }

        addon.findNext(term, {
            incremental: true,
            caseSensitive: false,
            regex: false,
        });
    };

    useEffect(() => {
        const listeners: Record<string, (s: string) => void> = {
            [SocketEvent.STATUS]: handlePowerChangeEvent,
            [SocketEvent.CONSOLE_OUTPUT]: handleConsoleOutput,
            [SocketEvent.INSTALL_OUTPUT]: handleConsoleOutput,
            [SocketEvent.TRANSFER_LOGS]: handleConsoleOutput,
            [SocketEvent.TRANSFER_STATUS]: handleTransferStatus,
            [SocketEvent.DAEMON_MESSAGE]: (line) => handleConsoleOutput(line, true),
            [SocketEvent.DAEMON_ERROR]: handleDaemonErrorOutput,
        };

        if (connected && instance) {
            // Do not clear the console if the server is being transferred.
            if (!isTransferring) {
                terminal.clear();
            }

            Object.keys(listeners).forEach((key: string) => {
                const listener = listeners[key];
                if (listener === undefined) {
                    return;
                }

                instance.addListener(key, listener);
            });
            instance.send(SocketRequest.SEND_LOGS);
        }

        return () => {
            if (instance) {
                Object.keys(listeners).forEach((key: string) => {
                    const listener = listeners[key];
                    if (listener === undefined) {
                        return;
                    }

                    instance.removeListener(key, listener);
                });
            }
        };
    }, [connected, instance]);

    return (
        <div className='bg-gradient-to-b from-[#ffffff08] to-[#ffffff05] border-[1px] border-[#ffffff12] rounded-xl hover:border-[#ffffff20] transition-all duration-150 overflow-hidden shadow-sm'>
            <div className='relative'>
                <SpinnerOverlay visible={!connected} size={'large'} />
                <div className='h-[340px] sm:h-[460px] p-3 sm:p-4 overflow-hidden flex flex-col'>
                    <div className='mb-3 flex items-center gap-2'>
                        <div className='flex h-10 items-center gap-2 rounded-lg bg-[#1b1b1b] px-3 text-sm text-zinc-300 border border-[#ffffff11] focus-within:border-[#ffffff33] flex-1'>
                            <Magnifier width={18} height={18} className='text-white/90 shrink-0' />
                            <input
                                type='text'
                                value={searchQuery}
                                onChange={(e) => {
                                    const value = e.target.value;
                                    setSearchQuery(value);
                                    handleSearch(value);
                                }}
                                placeholder='Search all logs...'
                                className='w-full bg-transparent text-sm text-zinc-100 placeholder-zinc-500 outline-none border-0 font-normal'
                            />
                        </div>
                        <div className='flex h-10 items-center text-sm text-zinc-300'>
                            <div className='flex h-10 items-center gap-1 rounded-lg bg-[#1b1b1b] border border-[#ffffff11]'>
                                <button
                                    type='button'
                                    onClick={() => {
                                        const term = lastSearchTerm.current.trim();
                                        const addon = searchAddonRef.current;
                                        if (!addon || !term) return;
                                        addon.findPrevious(term, {
                                            incremental: true,
                                            caseSensitive: false,
                                            regex: false,
                                        });
                                    }}
                                    className='inline-flex h-full w-10 items-center justify-center rounded-md bg-transparent hover:bg-[#2a2a2a] border border-transparent hover:border-[#ffffff33] disabled:opacity-40 disabled:cursor-default'
                                    disabled={!matchCount}
                                >
                                    <ChevronLeft width={18} height={18} className='text-zinc-200' />
                                </button>
                                <span className='min-w-[2.1rem] text-center text-sm tabular-nums'>{matchCount}</span>
                                <button
                                    type='button'
                                    onClick={() => {
                                        const term = lastSearchTerm.current.trim();
                                        const addon = searchAddonRef.current;
                                        if (!addon || !term) return;
                                        addon.findNext(term, {
                                            incremental: true,
                                            caseSensitive: false,
                                            regex: false,
                                        });
                                    }}
                                    className='inline-flex h-full w-10 items-center justify-center rounded-md bg-transparent hover:bg-[#2a2a2a] border border-transparent hover:border-[#ffffff33] disabled:opacity-40 disabled:cursor-default'
                                    disabled={!matchCount}
                                >
                                    <ChevronRight width={18} height={18} className='text-zinc-200' />
                                </button>
                                <button
                                    type='button'
                                    onClick={copyConsole}
                                    className='inline-flex h-full w-10 items-center justify-center rounded-md bg-transparent hover:bg-[#2a2a2a] border border-transparent hover:border-[#ffffff33]'
                                >
                                    <Copy width={18} height={18} className='text-zinc-200' />
                                </button>
                                <button
                                    type='button'
                                    onClick={downloadConsole}
                                    className='inline-flex h-full w-10 items-center justify-center rounded-md bg-transparent hover:bg-[#2a2a2a] border border-transparent hover:border-[#ffffff33]'
                                >
                                    <ArrowDownToLine width={18} height={18} className='text-zinc-200' />
                                </button>
                            </div>
                        </div>
                    </div>
                    <div style={stripedBackgroundStyle} className='h-full w-full overflow-hidden'>
                        <div ref={ref} className='h-full w-full' />
                    </div>
                </div>
                {canSendCommands && (
                    <div className='relative border-t-[1px] border-[#ffffff11] bg-[#0f0f0f]'>
                        <div className='flex items-center gap-1.5 px-1 py-2 sm:px-3 sm:py-2 text-xs sm:text-sm text-zinc-100'>
                            <span className='inline-flex h-7 w-7 items-center justify-center text-zinc-200'>
                                <TerminalIcon width={18} height={18} />
                            </span>
                            <input
                                className='w-full h-full bg-transparent font-mono text-xs sm:text-sm text-zinc-100 placeholder-zinc-500 border-0 outline-none focus:ring-0 focus:outline-none'
                                type='text'
                                placeholder='Enter a command...'
                                aria-label='Console command input.'
                                disabled={!instance || !connected}
                                onKeyDown={handleCommandKeyDown}
                                autoCorrect='off'
                                autoCapitalize='none'
                            />
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

export default Console;
