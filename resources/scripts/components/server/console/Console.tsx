import { FitAddon } from '@xterm/addon-fit';
import { SearchAddon } from '@xterm/addon-search';
import { WebLinksAddon } from '@xterm/addon-web-links';
import { ITerminalOptions, Terminal } from '@xterm/xterm';
import '@xterm/xterm/css/xterm.css';
import clsx from 'clsx';
import debounce from 'debounce';
import { Magnifier } from '@gravity-ui/icons';
import { useEffect, useMemo, useRef, useState } from 'react';

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
    fontFamily: 'ui-monospace, SFMono-Regular, "SF Mono", Consolas, "Liberation Mono", Menlo, monospace',
    theme: theme,
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
    const lastSearchTerm = useRef<string>('');
    const serverId = ServerContext.useStoreState((state) => state.server.data!.id);
    const isTransferring = ServerContext.useStoreState((state) => state.server.data!.isTransferring);
    const [history, setHistory] = usePersistedState<string[]>(`${serverId}:command_history`, []);
    const [historyIndex, setHistoryIndex] = useState(-1);

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
                fitAddonRef.current.fit();
            }
        }, 100),
    );

    const handleSearch = (value: string) => {
        const term = value.trim();
        const addon = searchAddonRef.current;

        if (!addon) {
            return;
        }

        if (!term) {
            lastSearchTerm.current = '';
            // Clear existing search highlights if supported by this SearchAddon version.
            // @ts-expect-error - clearDecorations may not exist on older versions.
            if (typeof (addon as any).clearDecorations === 'function') {
                (addon as any).clearDecorations();
            }
            return;
        }

        lastSearchTerm.current = term;
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
                <div className='bg-[#131313] h-[340px] sm:h-[460px] p-3 sm:p-4 overflow-hidden flex flex-col'>
                    <div
                        className='mb-4 flex items-center gap-3 rounded-lg bg-[#1b1b1b] px-4 py-2 text-sm text-zinc-300 border border-[#ffffff11] focus-within:border-[#ffffff33]'
                    >
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
                    <div className='h-full w-full'>
                        <div ref={ref} className='h-full w-full' />
                    </div>
                </div>
                {canSendCommands && (
                    <div className='relative border-t-[1px] border-[#ffffff11] bg-[#0f0f0f]'>
                        <input
                            className='w-full bg-transparent px-3 py-2.5 sm:px-4 sm:py-3 font-mono text-xs sm:text-sm text-zinc-100 placeholder-zinc-500 border-0 outline-none focus:ring-0 focus:outline-none focus:bg-[#1a1a1a] transition-colors duration-150'
                            type='text'
                            placeholder='Enter a command...'
                            aria-label='Console command input.'
                            disabled={!instance || !connected}
                            onKeyDown={handleCommandKeyDown}
                            autoCorrect='off'
                            autoCapitalize='none'
                        />
                    </div>
                )}
            </div>
        </div>
    );
};

export default Console;
