import { usePage } from '@inertiajs/react';
import FenetreChat from '../../Components/FenetreChat';
import LayoutPublic from '../../Layouts/LayoutPublic';
import { useT } from '../../lib/i18n';

export default function Chat() {
    const t = useT();
    const { assistant } = usePage().props.feggu;

    return (
        <LayoutPublic titre={t('chat_titre')} sansBulle>
            <div className="mx-auto flex max-w-2xl flex-col px-4 py-10 sm:px-6">
                <h1 className="flex flex-wrap items-center gap-3 text-4xl font-extrabold">
                    {t('chat_titre')}
                    {assistant && <span className="rounded-full bg-orange px-3 py-1 font-sans text-sm font-bold text-white">{t('chat_assistante')}</span>}
                </h1>
                <p className="mt-2 text-gris">{t(assistant ? 'chat_texte_ia' : 'chat_texte')}</p>
                <div className="mt-6"><FenetreChat /></div>
            </div>
        </LayoutPublic>
    );
}
