// Suggestions only: every field remains editable before it reaches the server.
export function parseBusinessCard(input) {
    const text = input.replace(/[٠-٩۰-۹]/g, digit => String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit) >= 0
        ? '٠١٢٣٤٥٦٧٨٩'.indexOf(digit) : '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)))
        .replace(/[^\S\r\n]+/g, ' ').trim();
    const email = text.match(/[\p{L}\p{N}.!#$%&'*+/=?^_`{|}~-]+@[\p{L}\p{N}-]+(?:\.[\p{L}\p{N}-]+)+/u)?.[0] ?? '';
    const phones = [...text.matchAll(/(?<![\p{L}\p{N}])\+?\d[\d ()\-.]{5,}\d(?![\p{L}\p{N}])/gu)]
        .map(match => match[0].trim()).filter(phone => {
            const length = phone.replace(/\D/g, '').length;
            return length >= 7 && length <= 15;
        }).sort((a, b) => Number(b.startsWith('+')) - Number(a.startsWith('+')) || b.length - a.length);
    const website = text.replace(email, '').match(/(?:https?:\/\/|www\.)?[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\.(?:com|net|org|io|iq|co|biz|me|info|tech)(?:\/[^\s]*)?/i)?.[0] ?? '';
    const lines = text.split(/\r?\n/).map(line => line.trim()).filter(Boolean);
    const semantic = lines.filter(line => ![email, website, ...phones].some(value => value && line.includes(value)));
    const companyPattern = /\b(company|group|trading|solutions?|services?|clinic|hospital|university|factory|industr(?:y|ies)|construction|contracting|holding|enterprise|international|agency|studio|cent(?:er|re)|plast(?:ic|ics)?|tech(?:nology|nologies)?|llc|ltd|inc)\b|شركة|شركه|مؤسسة|مجموعة|عيادة|مستشفى|جامعة|مصنع|کۆمپانیا|كۆمپانيا|گروپ|نەخۆشخانە|زانکۆ/iu;
    const jobPattern = /\b(manager|director|engineer|sales|marketing|founder|owner|ceo|cfo|cto|doctor|consultant|specialist|architect|accountant)\b|rêveber|endezyar|firotan|doktor|مدير|مهندس|دكتور|طبيب|مبيعات|تسويق|رئيس|مستشار|بەڕێوەبەر|ئەندازیار|دکتۆر|فرۆشتن|خاوەن/iu;
    const addressPattern = /\b(address|street|road|building|floor|office|iraq|baghdad|erbil|sulaymaniyah|duhok)\b|عراق|بغداد|أربيل|اربيل|السليمانية|دهوك|شارع|طابق|عنوان|کوردستان|هەولێر|سلێمانی|دهۆک|شەقام/iu;
    const domainStem = (email.split('@')[1] ?? website.replace(/^https?:\/\//i, '').replace(/^www\./i, ''))
        .split(/[./]/)[0]?.replace(/[^\p{L}\p{N}]/gu, '').toLocaleLowerCase() ?? '';
    let companyIndex = semantic.findIndex(line => companyPattern.test(line));
    if (companyIndex < 0 && domainStem.length >= 4) {
        companyIndex = semantic.findIndex(line => line.replace(/[^\p{L}\p{N}]/gu, '').toLocaleLowerCase() === domainStem);
    }
    const jobIndex = semantic.findIndex(line => jobPattern.test(line));
    const company = companyIndex >= 0 ? semantic[companyIndex] : '';
    const job = jobIndex >= 0 ? semantic[jobIndex] : '';
    const address = semantic.find(line => line !== company && line !== job && addressPattern.test(line)) ?? '';
    const honorificPattern = /^(?:dr\.?|doctor|eng\.?|engineer|mr\.?|mrs\.?|ms\.?|د\.?|دكتور|الدكتور|مهندس|المهندس|دکتۆر)\s+/iu;
    const name = semantic.map((line, index) => {
        const hasHonorific = honorificPattern.test(line);
        const candidate = line.replace(honorificPattern, '').trim();
        const words = candidate.split(/\s+/u);
        let score = hasHonorific ? 100 : 0;
        if (index === jobIndex - 1) score += 80;
        if (companyIndex >= 0 && index > companyIndex) score += 15;
        const emailName = email.split('@')[0]?.replace(/[^\p{L}\p{N}]/gu, '').toLocaleLowerCase() ?? '';
        if (emailName.length >= 4 && words.some(word => emailName.includes(word.replace(/[^\p{L}\p{N}]/gu, '').toLocaleLowerCase()))) score += 45;
        if (/^[A-Z\s.'’-]+$/.test(candidate)) score -= 35;
        return { candidate, index, words, score };
    }).filter(({ candidate, words, index }) => ![companyIndex, jobIndex].includes(index)
        && candidate !== address
        && !/\d/u.test(candidate)
        && !companyPattern.test(candidate)
        && !addressPattern.test(candidate)
        && words.length >= 2
        && words.length <= 5)
        .sort((a, b) => b.score - a.score || b.index - a.index)[0]?.candidate ?? '';
    const [first = '', ...last] = name.split(/\s+/u);
    return {
        first_name: first.slice(0, 100), last_name: last.join(' ').slice(0, 100),
        job_title: job.slice(0, 120), email: email.toLowerCase().slice(0, 255),
        phone: (phones[0] ?? '').slice(0, 40), company_name: company.slice(0, 160),
        notes: [website && `Website: ${website}`, address && `Address: ${address}`].filter(Boolean).join('\n').slice(0, 2000),
    };
}
