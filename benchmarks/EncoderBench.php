<?php

use Beau\CborPHP\CborEncoder;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

class EncoderBench
{
    #[Revs(1000), Iterations(5), Warmup(2)]
    public function benchEncodeSmallInt(): void
    {
        CborEncoder::encode(42);
    }

    #[Revs(1000), Iterations(5), Warmup(2)]
    public function benchEncodeNegativeInt(): void
    {
        CborEncoder::encode(-100);
    }

    #[Revs(1000), Iterations(5), Warmup(2)]
    public function benchEncodeLargeInt(): void
    {
        CborEncoder::encode(1000000);
    }

    #[Revs(1000), Iterations(5), Warmup(2)]
    public function benchEncodeFloat(): void
    {
        CborEncoder::encode(3.14159265358979);
    }

    #[Revs(1000), Iterations(5), Warmup(2)]
    public function benchEncodeString(): void
    {
        CborEncoder::encode("Hello, World!");
    }

    #[Revs(1000), Iterations(5), Warmup(2)]
    public function benchEncodeLargeString(): void
    {
        CborEncoder::encode("Flibber flabber wobblewix dranted across the murple sky. Snizzlefrap twanged lightly on the corners of a purple afternoon. Everything smelled faintly of zigzag marmalade. Snorfle tapple crindlepop shimmered in the distant thrum. A wobbernock blinked twice and forgot its own elbows. The air hummed in sideways spirals. Crimble dorf spun a ladder out of marshlight and thistledown. Breezy narplets clung to the ceiling of nowhere. Someone whispered “plankle” and vanished politely. Plonky dribblethorp meandered through a pocket of elastic thunder. The clouds wore waistcoats of lemon felt. Time hiccupped and excused itself. Zindlefork ramblequash jittered under a blanket of teal arithmetic. Blips of candied static popped merrily. The horizon folded into a paper teacup. Quabble snint whorled about in buttery zigzags. A flanterdash tapped rhythms on invisible glass. The moon yawned in lowercase. Merny quastle flickered like a candle made of soup. Brintle shards chimed against a velvet staircase. Nobody remembered where the floor had gone. Tufflewink sproon drifted between paragraphs of damp starlight. Cranky doodlefarms sprouted polka-dotted umbrellas. The breeze tasted like sideways laughter. Brindlewop clattered through a corridor of humming pillows. A spanglecart delivered parcels of mild astonishment. The lamps blinked in cursive. Sprocketty loomflare ticked gently beside a puddle of ticking moss. Frabble reeds swayed in synchronized confusion. Everything leaned slightly to the left. Lumblefizz cracked open a jar of portable dusk. Skitterplum bees negotiated with a tangerine compass. The carpet purred in octaves. Crattlewink jorped along a spiral of toasted rain. Wibblecrest feathers floated up instead of down. The teapot considered a career in geology. Vorny splindle snapped a ribbon of audible chalk. Plimsy droplets arranged themselves alphabetically. A distant door applauded softly. Pibblegrank murmured beneath a chandelier of borrowed freckles. Thrumble knots tied themselves into polite bows. The staircase smelled of cinnamon static. Jantlewhip corbled across a meadow of folded mirrors. Snibbletunes echoed in rectangular circles. The grass practiced speaking fluent marmot. Worbly tanglefork shivered with fluorescent hiccups. A mizzlecraft parked beside a stack of invisible sandwiches. Noon arrived wearing mittens. Nacklefrim bounced lightly on a trampoline of antique fog. Crimsy lanterns blinked in triangular sighs. The wallpaper giggled in italics. Fuzzlecrank jimmied open a window to the underbutter. Glinty sproons fluttered like metallic moths. The wind tied ribbons around its own ankles. Grindlewex pranced across a checkerboard of melted lullabies. Snarpish crumbs formed a parliament of crumbs. The ceiling exhaled peppermint thunder. Hobbly drent stitched pockets into the afternoon. Quimble flakes drifted through a harp-shaped alley. The shadows practiced their signatures. Kremblefarn polished a comet with a woolen whisper. Blonty rails curved into speculative geometry. The sidewalk hummed in minor key. Dazzlewick thrumpled under a cascade of apricot foghorns. Twindle jars rattled with luminous marbles. The horizon sneezed confetti. Yonderly sprack leaned against a fence of liquid brass. Crindleboots marched in ceremonious zigzags. A pebble recited dramatic poetry to a sock. Blimblequark twirled a baton made of sugared dusk. Flanterbells chimed in reversible echoes. The river practiced standing still. Snizzlefop darted through a hedgerow of lavender brackets. Pranglefish debated the ethics of marmalade. The mailbox blinked twice and blushed. Tronkly varp unfolded a map of edible thunder. Jibblefrogs harmonized in translucent hats. The cobblestones tasted faintly of plum arithmetic. Velmish cronk scribbled spirals on a loaf of wind. Snarpets rattled like optimistic dice. The lamplight stretched into a polite yawn. Cramblethud pirouetted atop a drum of sugared fog. Wizzlequins traded riddles for teaspoons. The attic floated three inches higher. Oozlet framble dripped neon syllables into a wicker cloud. Thistlefizz clapped in gelatinous approval. The doorknob considered a sabbatical. Pranglewisp cartwheeled through a corridor of sideways rain. Jorpish petals fluttered in disciplined chaos. The kettle hummed in ultraviolet. Whindlecrash stacked cushions of electric marmot. Plindle sparks fizzed in courteous patterns. The mirror tried on a new horizon. Xarny bliff shuffled cards made of distant Tuesdays. Crumpetle vines curled into geometric lullabies. The chimney whispered recipes for stardust. Glomblefret shimmered beneath a velvet avalanche of teaspoons. Snandle clocks ticked in polite disagreement. The rug attempted a small concerto. Zaffry quindlehop skipped across a pond of luminous ink. Frabblecorn popped in symmetrical astonishment. The trees wore spectacles of dew. Quindlefarn drizzled honeyed static over a brass afternoon. Plimsy wockets napped in orderly diagonals. The gate practiced a waltz with the wind. Rampledink fluttered beneath a chandelier of citrus whispers. Crankle looms wove blankets of audible silk. The hallway tasted like comet jam. Thistlewump jangled softly in a pocket of elastic dawn. Wibblethrush feathers scribbled notes on the breeze. The pantry glowed with alphabetical soup. Borkly snarp unspooled a ribbon of sideways thunder. Glintle jars chimed with minty applause. The staircase blinked in grayscale. Cinderoo flamble perched atop a cushion of ticking petals. Drizzlequark hummed in spherical harmony. The wallpaper folded into a gentle smirk. Plimblewex drifted through a meadow of humming parentheses. Snorfish lanterns bobbed in marmalade twilight. Everything sighed in soft, reversible sparkles.");
    }

    #[Revs(1000), Iterations(5), Warmup(2)]
    public function benchEncodeBoolean(): void
    {
        CborEncoder::encode(true);
    }

    #[Revs(1000), Iterations(5), Warmup(2)]
    public function benchEncodeNull(): void
    {
        CborEncoder::encode(null);
    }

    #[Revs(500), Iterations(5), Warmup(2)]
    public function benchEncodeArray(): void
    {
        CborEncoder::encode([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
    }

    #[Revs(500), Iterations(5), Warmup(2)]
    public function benchEncodeMap(): void
    {
        CborEncoder::encode([
            "name" => "Alice",
            "age" => 30,
            "active" => true,
            "score" => 9.5,
        ]);
    }

    #[Revs(200), Iterations(5), Warmup(2)]
    public function benchEncodeNestedStructure(): void
    {
        CborEncoder::encode([
            "users" => [
                ["id" => 1, "name" => "Alice", "scores" => [95, 87, 92]],
                ["id" => 2, "name" => "Bob", "scores" => [78, 85, 91]],
                ["id" => 3, "name" => "Charlie", "scores" => [88, 92, 95]],
            ],
            "total" => 3,
            "active" => true,
        ]);
    }

    #[Revs(100), Iterations(5), Warmup(2)]
    public function benchEncodeLargeArray(): void
    {
        CborEncoder::encode(range(1, 100));
    }
}